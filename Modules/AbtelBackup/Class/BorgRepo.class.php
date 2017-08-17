<?php
class BorgRepo extends genericClass {
    public function Save() {
        $new = false;
        if (!$this->Id){
            //nouvelle machine
            $new = true;
        }
        parent::Save();
        if ($new){
            //creation du depot
            return $this->createDepot();
        }
        return true;
    }
    public function createDepot () {
        //test de l'existence du chemin
        if (!file_exists($this->Path)){
            //création du chemin
            try {
                mkdir($this->Path, 0777, true);
            }catch (Exception $e){
                $this->addError(array('Message'=>'Impossible de créer le dossier '.$this->Path.'. Détail: '.$e->getMessage()));
                return false;
            }
        }
        //intialisation du dépot
        try {
            AbtelBackup::localExec('export BORG_PASSPHRASE=\''.BORG_SECRET.'\' && borg init ' . $this->Path);
        }catch (Exception $e) {
            $this->addError(array('Message'=>'Impossible de créer le dépôt  Borg '.$this->Path.'. Détail: '.$e->getMessage()));
            return false;
        }
        return true;
    }
}