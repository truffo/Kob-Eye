<?php
class SambaJob extends genericClass {
    private $TotalProgression = 100;
    public static function execute() {
        //intialisation des dates
        $d = time();
        $week = array('Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche');
        $weekday = $week[date('w',$d)];
        $hour = date('H',$d);
        $minute = intval(date('i',$d));
        $month = intval(date('m',$d));
        $monthday = date('j',$d);
        $jobs = Sys::getData('AbtelBackup','SambaJob/Enabled=1&(!Minute=*+Minute='.$minute.'!)&(!Heure=*+Heure='.$hour.'!)&(!Jour=*+Jour='.$monthday.'!)&(!Mois=*+Mois='.$month.'!)&(!(!Lundi=0&Mardi=0&Mercredi=0&Jeudi=0&Vendredi=0&Samedi=0&Dimanche=0!)+(!'.$weekday.'=1!)!)');

        foreach ($jobs as $j) {
            $j->run();
        }
    }
    public function createActivity($title,$vm) {

        $act = genericClass::createInstance('AbtelBackup','Activity');
        $act->addParent($this);
        $act->addParent($vm);
        $act->Titre = '[SAMBAJOB] '.date('d/m/Y H:i:s').' > '.$this->Titre.' > '.$title;
        $act->Started = true;
        $act->Progression = 0;
        $act->Save();
        return $act;
    }
    public function runNow() {
        if ($this->Running){
            $this->addError(Array('Message'=>'Impossible de démarrer le job. Il est déjà en cours.'));
            return false;
        }
        $cmd = 'bash -c "exec nohup setsid php cron.php backup.abtel.local AbtelBackup/SambaJob/'.$this->Id.'/run.cron > /dev/null 2>&1 &"';
        exec($cmd);
        return true;
    }
    /**
     * stop
     * Stoppe un job de backup
     */
    public function stop()
    {
        $vms = Sys::getData('AbtelBackup', 'SambaJob/' . $this->Id . '/SambaShare');
        foreach ($vms as $v) {
            $act = $this->createActivity($v->Titre . ' > Arret Utilisateur: Step ' . $this->Step, $v);
            $act->addDetails($v->Titre . "Arret Utilisateur", 'red', true);
            $act->setProgression(100);
            $act->Success = true;
            $act->Save();
        }
        switch ($this->Step) {
            case 1:
                $this->addError(Array('Message' => 'Impossible de stopper le job pendant l\'initialisation.'));
                return false;
                break;
            case 2:
                $this->addError(Array('Message' => 'Impossible de stopper le job pendant la configuration.'));
                return false;
                break;
            case 3:
                if (AbtelBackup::getPid('borg')) {
                    $this->clearAct(false);
                    AbtelBackup::localExec('sudo killall -9 borg');
                    $vms = Sys::getData('AbtelBackup', 'SambaJob/' . $this->Id . '/SambaShare');
                    foreach ($vms as $v) {
                        $borg = $v->getOneParent('BorgRepo');
                        AbtelBackup::localExec('sudo rm ' . $borg->Path . '/lock.* -Rf');
                    }
                    $this->addSuccess(Array('Message' => 'Déduplication stoppée avec succès.'));
                } else {
                    $this->clearAct(true);
                    $this->addWarning(Array('Message' => 'Le processus n\'a pas été trouvé.'));
                }
                $this->Running = false;
                parent::Save();
                return true;
                break;
            default:
                $this->clearAct(true);
                $this->resetState();
                parent::Save();
                $this->addSuccess(Array('Message' => 'Job stoppé avec succès.'));
                return true;
                break;
        }
    }
    /**
     * run
     * Demarre ou resume un job de backup de vm
     */
    public function run() {
        //test running
        if ($this->Running) {
            $act = $this->createActivity(' > Impossible de démarrer, le job est déjà en cours d\'éxécution');
            $act->Terminate();
            return;
        }
        $this->resetState();
        $this->Running = true;
        parent::Save();
        //init
        Klog::l('DEBUG demarrage samba');
        $GLOBALS['Systeme']->Db[0]->query("SET AUTOCOMMIT=1");
        //pour chaque partage
        $sss = Sys::getData('AbtelBackup','SambaJob/'.$this->Id.'/SambaShare');
        $this->TotalProgression =  Sys::getCount('AbtelBackup','SambaJob/'.$this->Id.'/SambaShare')*100;

        foreach ($sss as $ss){
            Klog::l('DEBUG share ==> '.$ss->Id.' STEP: '.$this->Step);
            //définition de la vm en cours
            $this->setStep(1);
            $this->setCurrentSambaShare($ss->Id);
            $dev = $ss->getOneParent('SambaDevice');
            $borg = $dev->getOneParent('BorgRepo');
            try {
                //initialisation
                if (intval($this->Step)<=1){
                    unset($act);
                    $act = $this->createActivity($ss->Titre.' > Initialisation du job Samba',$ss);
                    $this->initJob($ss,$dev,$act);
                }

                //montage
                if (intval($this->Step)<=2){
                    unset($act);
                    $act = $this->createActivity($ss->Titre.' > Montage du partage Samba',$ss);
                    $act = $this->mountJob($ss,$dev,$act);
                }

                //déduplication
                if (intval($this->Step)<=3){
                    unset($act);
                    $act = $this->createActivity($ss->Titre.' > Déduplication vmjob',$ss);
                    $act = $this->deduplicateJob($ss,$dev,$borg,$act);
                }

            }catch (Exception $e){
                if(!$act) $act = $this->createActivity($ss->Titre.' > Exception: Step '.$this->Step,$ss);
                $act->addDetails($ss->Titre." ERROR => ".$e->getMessage(),'red');
                $act->Terminated = true;
                $act->Errors = true;
                $act->Save();
                //opération terminée
                $this->Running = false;
                $this->Errors = true;
                parent::Save();
                return;
            }
        }
        //opération terminée
        $this->resetState();
    }
    /**
     * setStep
     * Définie l'étape en cours.
     * Permet une reprise en repartant de l'étape en erreur
     */
    private function setStep($step){
        $this->Step = $step;
        parent::Save();
    }
    /**
     * setCurrentVm
     * Déinfition de la vm en cours de traitement
     */
    private function setCurrentSambaShare($ss){
        $this->CurrentShare = $ss;
        parent::Save();
    }
    /**
     * Nettoyage de l'esx et du stor elocal
     */
    private function initJob($ss,$dev,$act) {
        Klog::l('DEBUG Test INIT JOB');
        $this->setStep(1); //Initialisation
        $act->addDetails('Création des dossier','yellow');
        AbtelBackup::localExec("if [ ! -d '/backup/samba/".$dev->IP."/".$ss->Partage."' ]; then mkdir -p '/backup/samba/".$dev->IP."/".$ss->Partage."'; fi");
        //démontage si toujours présent
        try {
            AbtelBackup::localExec("sudo umount '/backup/samba/" . $dev->IP . "/" . $ss->Partage . "'");
        } catch (Exception $e){

        }
        //nettoyage sauvegarde précédentes
        $act->addProgression(100);
        $this->addProgression(10);
        parent::Save();
        return $act;
    }
    /**
     * mountJob
     * Montage du partage
     */
    private function mountJob($ss,$dev,$act){
        $this->setStep(2); //Montage
        $act->addDetails('-> Montage du partage '.$ss->Titre,'yellow');
        $cmd = 'sudo mount -t cifs -v "//'.$dev->IP.'/'.$ss->Partage.'" \'/backup/samba/'.$dev->IP.'/'.$ss->Partage."' -o ";

        if (!empty($dev->Login)) {
            $cmd .= "user='" . $dev->Login . "',pass='" . $dev->Password . "'";
            if (!empty($dev->Domain))
                $cmd .= ",dom='".$dev->Domain."'";
        }
        $cmd.=((!empty($dev->Login))?",":"")."uid=1000,gid=1000";
        $act->addDetails('CMD: '.$cmd);
        //AbtelBackup::localExec($cmd);
        shell_exec($cmd);
        $act->addProgression(100);
        $this->addProgression(20);
        parent::Save();
        return $act;
    }
    /**
     * deduplicateJob
     * Déduplication de la vm
     */
    private function deduplicateJob($ss,$dev,$borg,$act){
        $this->setStep(3); //'Déduplication'
        AbtelBackup::localExec('borg break-lock '.$borg->Path); //Supression des locks borg
        //AbtelBackup::localExec('borg delete --cache-only '.$borg->Path); //Supression du cache eventuellement corrompu
        AbtelBackup::localExec('sudo chown -R backup:backup '.$borg->Path.''); //On s'assure que les fichiers borg ne soient pas en root
        $total = AbtelBackup::getSize('/backup/samba/'.$dev->IP.'/'.$ss->Partage.'');
        $act->addDetails($ss->Titre.' ---> TOTAL (Mo):'.$total);
        $act->addDetails($ss->Titre.' ---> déduplication du partage');

        //Recup taille pour graphique/progression
        $this->Size = $total;

        $point = time();
        //file_put_contents('tototoottoto',"export BORG_PASSPHRASE='".BORG_SECRET."' && borg create --progress --compression lz4 ".$borg->Path."::".$point." '/backup/nfs/".$v->Titre.".tar'");
        $det = AbtelBackup::localExec("export BORG_PASSPHRASE='".BORG_SECRET."' && borg create --progress --compression lz4 ".$borg->Path."::".$point." '/backup/samba/".$dev->IP."/".$ss->Partage."'",$act,$total,'borg');

        //Recup taille pour graphique/progression
        $this->BackupSize = AbtelBackup::getSize($borg->Path);
        parent::Save();

        //création du point de restauration
        $dev->createRestorePoint($point,$det);
        $act->setProgression(100);
        $this->addProgression(80);
        return $act;
    }
    /**
     * resetState
     * Reinitialisation du job
     */
    private function resetState(){
        $this->setStep(0); //'Attente'
        $this->setCurrentSambaShare(0);
        $this->Running = false;
        $this->Progression = 0;
        parent::Save();
    }

    private function clearAct($full = true){
        $acts = $this->getChildren('Activity/Started=1&Errors=0&Success=0');
        foreach($acts as $act){
            //print_r($act);
            if($full)
                $act->Errors = 1;

            $act->addDetails(' ---> Arrêt Utilisateur','cyan',true);
            //print_r($act);

        }
    }
    private function addProgression($nb){
        $this->Progression += ($nb/$this->TotalProgression)*100;
    }
}