<?php
class VmJob extends genericClass {
    public static function execute() {
        //intialisation des dates
        $d = time();
        $week = array('Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche');
        $weekday = $week[date('w',$d)];
        $hour = date('H',$d);
        $minute = intval(date('i',$d));
        $month = intval(date('m',$d));
        $monthday = date('j',$d);
        $jobs = Sys::getData('AbtelBackup','VmJob/Enabled=1&(!Minute=*+Minute='.$minute.'!)&(!Heure=*+Heure='.$hour.'!)&(!Jour=*+Jour='.$monthday.'!)&(!Mois=*+Mois='.$month.'!)&(!(!Lundi=0&Mardi=0&Mercredi=0&Jeudi=0&Vendredi=0&Samedi=0&Dimanche=0!)+(!'.$weekday.'=1!)!)');

        foreach ($jobs as $j) {
            $j->run();
        }
    }
    public function createActivity($title) {
        $act = genericClass::createInstance('AbtelBackup','Activity');
        $act->addParent($this);
        $act->Titre = '[VMJOB] '.$this->Titre.' > '.$title;
        $act->Started = true;
        $act->Progression = 0;
        $act->Save();
        return $act;
    }
    public function run() {
        //init
        $GLOBALS['Systeme']->Db[0]->query("SET AUTOCOMMIT=1");
        //pour chaque vm
        $vms = Sys::getData('AbtelBackup','VmJob/'.$this->Id.'/EsxVm');
        foreach ($vms as $v){
            $esx = $v->getOneParent('Esx');
            $borg = $v->getOneParent('BorgRepo');
            $act = $this->createActivity('Nettoyage des archives');
            $act->addDetails('Suppression du script ghettoVCB','yellow');
            //nettoyage sauvegarde précédentes
            $act->addDetails('---> suppression du script ghettoVCB');
            $esx->remoteExec("if [ -f /ghettoVCB.sh ]; then rm /ghettoVCB.sh; fi");
            $act->addProgression(15);
            $act->addDetails('---> suppression des snapshots');
            $esx->remoteExec("vim-cmd vmsvc/snapshot.removeall ".$v->RemoteId." && sleep 5");
            $act->addProgression(15);
            $act->addDetails('---> suppression du fichier .work');
            $esx->remoteExec("if [ -d '/tmp/ghettoVCB.work' ]; then rm -Rf '/tmp/ghettoVCB.work'; fi");
            $act->addProgression(15);
            $act->addDetails('---> suppression de la complete');
            AbtelBackup::localExec("if [ -d '/backup/nfs/EsxVm/".$esx->IP."/".$v->Titre."' ]; then sudo rm -Rf '/backup/nfs/".$esx->IP."/".$v->Titre."'; fi");
            $act->addProgression(15);
            AbtelBackup::localExec("if [ -d '/backup/nfs/".$v->Titre."' ]; then sudo rm -Rf '/backup/nfs/".$v->Titre."'; fi");
            $act->addProgression(15);
            $act->addDetails('---> suppression archive');
            AbtelBackup::localExec("if [ -f '/backup/nfs/EsxVm/".$esx->IP."/".$v->Titre.".tar' ]; then sudo rm -f /backup/nfs/EsxVm/".$esx->IP."/".$v->Titre.".tar; fi");
            $act->addProgression(25);


            //creation de la configuration
            $config='VM_BACKUP_VOLUME=/vmfs/volumes/BORG/
DISK_BACKUP_FORMAT=thin
VM_BACKUP_ROTATION_COUNT=1
POWER_VM_DOWN_BEFORE_BACKUP=0
ENABLE_HARD_POWER_OFF=0
ITER_TO_WAIT_SHUTDOWN=3
POWER_DOWN_TIMEOUT=5
ENABLE_COMPRESSION=0
VM_SNAPSHOT_MEMORY=0
VM_SNAPSHOT_QUIESCE=0
ALLOW_VMS_WITH_SNAPSHOTS_TO_BE_BACKEDUP=0
ENABLE_NON_PERSISTENT_NFS=0
UNMOUNT_NFS=0
NFS_SERVER='.AbtelBackup::getMyIp().'
NFS_VERSION=nfs
NFS_MOUNT=/backup/nfs
NFS_LOCAL_NAME=BORG
NFS_VM_BACKUP_DIR=EsxVm/'.$esx->IP.'/
SNAPSHOT_TIMEOUT=15
EMAIL_ALERT=0
EMAIL_LOG=0
EMAIL_SERVER=
EMAIL_SERVER_PORT=25
EMAIL_DELAY_INTERVAL=1
EMAIL_TO=
EMAIL_ERRORS_TO=
EMAIL_FROM=
WORKDIR_DEBUG=0
VM_SHUTDOWN_ORDER=
VM_STARTUP_ORDER=
';
            $act = $this->createActivity('Configuration vmjob');
            $act->addDetails('-> configuration vmjob','yellow');
            try {
                $act->addDetails('---> creation de la config ghettoVCB');
                $esx->remoteExec("echo '$config' > /ghettovcb.conf");
                $act->addProgression(50);
                $act->addDetails('---> copy du script ghettoVCB');
                $esx->copyFile('ghettoVCB.sh');
                $act->addProgression(50);
                $act = $this->createActivity('Clonage vmjob');
                $act->addDetails('---> clonage de la vm');
                $esx->remoteExec('sh ghettoVCB.sh -m "' . $v->Titre . '" -g ghettovcb.conf',$act);
                $act = $this->createActivity('Compression vmjob');
                $act->addDetails('---> compression du clone');
                AbtelBackup::localExec("sudo tar cvSf '/backup/nfs/EsxVm/".$esx->IP."/".$v->Titre.".tar' '/backup/nfs/EsxVm/".$esx->IP."/".$v->Titre."/".$v->Titre."-A'");
                $act->addProgression(100);
                $act = $this->createActivity('Déduplication vmjob');
                $act->addDetails('---> déduplication de la vm');
                AbtelBackup::localExec("borg create --compression lz4 ".$borg->Path."::".time()." '/backup/nfs/EsxVm/".$esx->IP."/".$v->Titre.".tar'");
                $act->addProgression(100);
            }catch (Exception $e){
                $act->addDetails("ERROR => ".$e->getMessage(),'red');
                $act->Terminated = true;
                $act->Errors = true;
                $act->Save();
            }
        }
    }
}