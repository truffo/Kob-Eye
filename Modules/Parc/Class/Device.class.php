<?php
class Device extends genericClass{
    /*
     *
     * @override
     */
    function save(){
        if ($this->Id) {
            $this->checkGuacamoleConnections();
            $port_rdp = 12000 + $this->Id;
            $port_vnc = 22000 + $this->Id;
            $this->ConnectionType = 'R' . $port_rdp . '=localhost:3389,R' . $port_vnc . '=localhost:15900';
        }
        parent::save();
    }
    function getVersion($uuid) {
        /*if(!isset($uuid))
            return false;*/
        $prod = true;
        $Commands = "";
        $ConnectionType = "\r\nPorts=";
        //recherche de la machine
        $dev = Sys::getOneData('Parc','Device/Uuid='.$uuid);
        if (!$dev&&isset($uuid)){
            //si machine existe pas on la créé
            $this->getConfig($uuid);
            $dev = Sys::getOneData('Parc','Device/Uuid='.$uuid);
        }
        if ($dev){
            //mise à jour de la machine
            $dev->LastSeen = time();
            $dev->PublicIP = $_SERVER['REMOTE_ADDR'];
            $dev->Online = true;
            $dev->Save();
            if ($dev->ModeTest) $prod=false;
            if (isset($_GET['version'])&&$_GET['version']!=$dev->CurrentVersion){
                $dev->CurrentVersion = $_GET['version'];
                $dev->Save();
            }
            if (isset($_GET['bios'])&&$_GET['bios']!=$dev->CurrentVersion){
                $dev->SerialNumber = $_GET['bios'];
                $dev->Save();
            }
            //GEstion des commandes
            if ($dev->RestartTunnel){
                $Commands = "\r\nCommande=tunnel";
                $dev->RestartTunnel = false;
                $dev->Save();
            }
            $ConnectionType .= $dev->ConnectionType;
            //Mise à jour des devices offline
            $devs = Sys::getData('Parc','Device/LastSeen<'.(time()-60));
            foreach ($devs as $d) {
                $d->Online = false;
                $d->Save();
            }

        }
        //recherche de la version
        $log = Sys::getOneData('Parc','LogicielVersion/Release='.$prod);
        if (!$log) $log = Sys::getOneData('Parc','LogicielVersion/Release='.!$prod);
        return "Version=$log->Version
Install=http://".Sys::$domain."/$log->InstallFile
Service=http://".Sys::$domain."/$log->ServiceFile
Tunnel32=http://".Sys::$domain."/$log->TunnelFile
Tunnel64=http://".Sys::$domain."/$log->TunnelFile64
Vnc32=http://".Sys::$domain."/$log->VncFile
Vnc64=http://".Sys::$domain."/$log->VncFile64
VncDll32=http://".Sys::$domain."/$log->VncDllFile
VncDll64=http://".Sys::$domain."/$log->VncDllFile64
ZabbixAgent32=http://".Sys::$domain."/$log->ZabbixAgent32
ZabbixAgent64=http://".Sys::$domain."/$log->ZabbixAgent64$ConnectionType$Commands
Client=$dev->CodeClient
";
    }
    function getConfig($uuid) {

        if (empty($uuid)) return;
        $exists = Sys::getOneData('Parc','Device/Uuid='.$uuid);
        if ($exists){
            $port_rdp = 12000+$exists->Id;
            $port_vnc = 22000+$exists->Id;
            $exists->Nom = $_GET["name"];
            $exists->Description = $_GET["os"];
            if(isset($_GET["machine"]))
                $exists->Type = $_GET["machine"];
            $exists->ConnectionType = 'R'.$port_rdp.'=localhost:3389,R'.$port_vnc.'=localhost:15900';
            $exists->Save();
            $obj = $exists;
        }else{
            //creation du device
            $obj = genericClass::createInstance('Parc','Device');
            $obj->Nom = $_GET["name"];
            $obj->Description = $_GET["os"];
            $obj->Type = $_GET["machine"] || 'station';
            $obj->Uuid = $uuid;
            $obj->Save();
            $port_rdp = 12000+$obj->Id;
            $port_vnc = 22000+$obj->Id;
            if ($port_rdp==12000) die('ERROR');
            $obj->ConnectionType = 'R'.$port_rdp.'=localhost:3389,R'.$port_vnc.'=localhost:15900';

            //$obj->addParent('Parc/Client/2');
            $obj->Save();
        }
        //affectation du client
        $client = $_GET["clientid"];
        if (!empty($client)){
            $obj->CodeClient = $client;
            if (is_int($client)) {
                $cli = Sys::getOneData('Parc', 'Client/Id=' . $client);
            }else{
                $cli = Sys::getOneData('Parc','Client/~'.$client);
            }
            if ($cli) {
                $obj->addParent($cli);
            }
            $obj->Save();
        }
        return $obj->ConnectionType;
    }


    private function checkGuacamoleConnections()
    {
        $dbGuac = new PDO('mysql:host=10.0.97.5;dbname=guacamole', 'root', 'RsL5pfky', array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
        $dbGuac->query("SET AUTOCOMMIT=1");
        $dbGuac->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        //Vérif l'existence du client et le crée le cas échéant
        $cli = $this->getOneParent('Client');
        //klog::l('$cli',$cli);
        if (isset($cli->AccesUser) && $cli->AccesUser != '' && $cli->AccesUser != null && isset($cli->AccesPass) && $cli->AccesPass != '' && $cli->AccesPass != null) {
            $query = "SELECT * FROM `guacamole_user` WHERE username = '" . $cli->AccesUser . "'";
            $q = $dbGuac->query($query);
            $result = $q->fetchALL(PDO::FETCH_ASSOC);
            if (sizeof($result) > 0) {
                $usr = $result[0];
            } else {
                $query = "INSERT INTO `guacamole_user` (username,password_hash,password_date) VALUES ('" . $cli->AccesUser . "',UNHEX(SHA2('" . $cli->AccesPass . "',256)),'" . date("Y-m-d H:i:s") . "')";
                $q = $dbGuac->query($query);

                $query = "SELECT * FROM `guacamole_user` WHERE username = '" . $cli->AccesUser . "'";
                $q = $dbGuac->query($query);
                $result = $q->fetchALL(PDO::FETCH_ASSOC);
                $usr = $result[0];
            }
        } elseif (isset($cli->AccesUser) && $cli->AccesUser != '' && $cli->AccesUser != null && (!isset($cli->AccesPass) || $cli->AccesPass == '' || $cli->AccesPass == null)) {
            $cli->addError(array('Message' => 'La valeur du champ AccesPass est nulle ou non définie alors que le champ AccesUser est défini.', "Prop" => 'AccesPass'));
        }


        //Connection RDP
        if ($this->GuacamoleUrlRdp == "" || $this->GuacamoleUrlRdp == null || $this->GuacamoleIdRdp == "" || $this->GuacamoleIdRdp == null) {

            $query = "INSERT INTO `guacamole_connection` (connection_name,protocol,parent_id,max_connections,max_connections_per_user) VALUES ('" . $this->Nom . "_rdp','rdp',NULL,NULL,NULL)";
            $q = $dbGuac->query($query);
            $lid = $dbGuac->lastInsertId();

            $query = "INSERT INTO `guacamole_connection_parameter` (connection_id,parameter_name,parameter_value) VALUES ($lid,'hostname','127.0.0.1')";
            $q = $dbGuac->query($query);
            $port = 12000 + $this->Id;
            $query = "INSERT INTO `guacamole_connection_parameter` (connection_id,parameter_name,parameter_value) VALUES ($lid,'port','$port')";
            $q = $dbGuac->query($query);

            $this->GuacamoleIdRdp = $lid;
            $this->GuacamoleUrlRdp = base64_encode($lid . "\0" . 'c' . "\0" . 'mysql');

            $this->Save();
        } else {
            $query = "UPDATE `guacamole_connection` SET connection_name ='" . $this->Nom . "_rdp' WHERE connection_id =$this->GuacamoleIdRdp";
            $q = $dbGuac->query($query);

            $port = 12000 + $this->Id;
            $query = "UPDATE `guacamole_connection_parameter` SET parameter_value = '$port' WHERE connection_id=$this->GuacamoleIdRdp AND parameter_name='port'";
            $q = $dbGuac->query($query);
        }


        //Connection VNC
        if ($this->GuacamoleUrlVnc == "" || $this->GuacamoleUrlVnc == null || $this->GuacamoleIdVnc == "" || $this->GuacamoleIdVnc == null) {

            $query = "INSERT INTO `guacamole_connection` (connection_name,protocol,parent_id,max_connections,max_connections_per_user) VALUES ('" . $this->Nom . "_vnc','vnc',NULL,NULL,NULL)";


            $q = $dbGuac->query($query);
            $lid = $dbGuac->lastInsertId();

            $query = "INSERT INTO `guacamole_connection_parameter` (connection_id,parameter_name,parameter_value) VALUES ($lid,'hostname','127.0.0.1')";
            $q = $dbGuac->query($query);
            $port = 22000 + $this->Id;
            $query = "INSERT INTO `guacamole_connection_parameter` (connection_id,parameter_name,parameter_value) VALUES ($lid,'port','$port')";
            $q = $dbGuac->query($query);
            $query = "INSERT INTO `guacamole_connection_parameter` (connection_id,parameter_name,parameter_value) VALUES ($lid,'password','secret')";
            $q = $dbGuac->query($query);

            $this->GuacamoleIdVnc = $lid;
            $this->GuacamoleUrlVnc = base64_encode($lid . "\0" . 'c' . "\0" . 'mysql');

            $this->Save();
        } else {
            $query = "UPDATE `guacamole_connection` SET connection_name ='" . $this->Nom . "_vnc' WHERE connection_id =$this->GuacamoleIdVnc";
            $q = $dbGuac->query($query);

            $port = 22000 + $this->Id;
            $query = "UPDATE `guacamole_connection_parameter` SET parameter_value = '$port' WHERE connection_id=$this->GuacamoleIdVnc AND parameter_name='port'";
            $q = $dbGuac->query($query);

        }

        if (isset($usr)) {
            $query = "INSERT IGNORE INTO `guacamole_connection_permission` (user_id,connection_id,permission) VALUES ('" . $usr['user_id'] . "','" . $this->GuacamoleIdVnc . "','READ'),('" . $usr['user_id'] . "','" . $this->GuacamoleIdRdp . "','READ')";
            $q = $dbGuac->query($query);
        }

        return true;
    }

    private function removeGuacamoleConnection(){

    }

}