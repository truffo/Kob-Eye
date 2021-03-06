<?php
class Visite extends genericClass {

	function Save($mode = false) {
		$annee = Cadref::$Annee;
		if(!empty($this->Annee) && $this->Annee != $annee) {
			$this->addError(array("Message" => "Cette fiche ne peut être modifiée ($this->Annee)", "Prop" => ""));
			return false;			
		}
		$id = $this->Id;
		if(! $id) { //if($mode) {
			$this->Annee = $annee;
			$this->Utilisateur = Sys::$User->Initiales;
		}
		$this->Attentes = Sys::getCount('Cadref','Visite/'.$this->Id.'/Reservation/Attente=1&Supprime=0');
		$this->Inscrits = Sys::getCount('Cadref','Visite/'.$this->Id.'/Reservation/Attente=0&Supprime=0');

		$ret = parent::Save();
		if(! $id) {
			$lx = Sys::getData('Cadref','Lieu/Type=R');
			foreach($lx as $l) {
				$d = genericClass::createInstance('Cadref', 'Depart');
				$d->addParent($this);
				$d->addParent($l);
				$d->Save();
			}
		}
		return $ret;
	}

	function Delete() {
		$res = $this->getChildren('Reservation');
		if(count($res)) {
			$this->addError(array("Message" => "Cette fiche ne peut être supprimée", "Prop" => ""));
			return false;
		}
		$rec = $this->getChildren('Lieu');
		foreach($rec as $r)
			$r->Delete();
		$rec = $this->getChildren('Enseignant');
		foreach($rec as $r)
			$r->Delete();
		
		return parent::Delete();
	}
	
	function PrintVisite($obj) {
		require_once ('PrintVisite.class.php');

		$annee = Cadref::$Annee;
		$debut = isset($obj['Debut']) ? $obj['Debut'] : '';
		$fin = isset($obj['Fin']) ? $obj['Fin'] : '';
		$fin .= substr('ZZZZZZZZZZ', 0, 10-strlen($fin));
		$chauffeur = isset($obj['Chauffeur']) ? $obj['Chauffeur'] : false;
		
		$sql = "
select r.VisiteId, r.Prix+r.Assurance-r.Reduction as Montant, v.Visite, v.Libelle, v.DateVisite, e.Numero, e.Nom, e.Prenom, 
d.HeureDepart, l.Libelle as LibelleL, r.Notes
from `##_Cadref-Reservation` r
inner join `##_Cadref-Visite` v on v.Id=r.VisiteId
inner join `##_Cadref-Adherent` e on e.Id=r.AdherentId 
inner join `##_Cadref-Depart` d on d.Id=r.DepartId
inner join `##_Cadref-Lieu` l on l.Id=d.LieuId
where r.Annee=$annee and r.Supprime=0 and r.Attente=0 ";
		if($debut != '') $sql .= "and r.Visite>='$debut' ";
		if($fin != '') $sql .= "and r.Visite<='$fin' ";
		if($chauffeur) $sql .= "order by r.Visite, d.HeureDepart, l.Libelle, e.Nom, e.Prenom";
		else $sql .= "order by r.Visite, e.Nom, e.Prenom";

		$sql = str_replace('##_', MAIN_DB_PREFIX, $sql);
		$pdo = $GLOBALS['Systeme']->Db[0]->query($sql);
		if(! $pdo) return array('pdf'=>'', 'sql'=>$sql);;
		
		$pdf = new PrintVisite($chauffeur);
		$pdf->SetAuthor("Cadref");
		$pdf->SetTitle(iconv('UTF-8','ISO-8859-15//TRANSLIT','Visites guidées'));

		$pdf->PrintLines($pdo);

		$file = 'Home/tmp/VisiteGuidee'.date('YmdHi').'.pdf';
		$pdf->Output(getcwd() . '/' . $file);
		$pdf->Close();

		return array('pdf'=>$file, 'sql'=>$sql);
	}

}


