<?php

//require_once('Class/Lib/pdfb1/pdfb.php');
require_once('Class/Lib/pdfb/fpdf_fpdi/fpdf.php');

class PrintReglement extends FPDF {
	
	private $user;
	private $debut;
	private $fin;
	private $left = 5;
	private $head;
	private $align;
	private $width;
	private $posy;
	private $totaux = [0, 0, 0];
	private $total = faux;
	private $mode = 0;  // 0:reglement, 1:differes, 2:non encaisses
	private $titre;
	
	
	function PrintReglement($mode, $user, $debut, $fin) {
		parent::__construct('P', 'mm', 'A4');
		$this->AcceptPageBreak(true, 12);

		$this->head = array('','Util','Date','Chèque','Espèces','Carte','','Adhérent');
		$this->width = array(10,10,25,19,19,19,3,110);
		$this->align = array('L','L','L','R','R','R','L','L');

		$this->mode = $mode;
		$this->user = $user;
		$this->debut = $debut;
		$this->fin = $fin;

		if($this->mode) {  // masque la colonne espèces
			$this->head[4] = '';
			$this->width[4] = 0.01;
		}
		$titre = "CADREF : Règlements ";
		switch($this->mode) {
			case 0: $titre .= "du ".$this->debut." au ".$this->fin."  Utilisateur : ".$this->user; break;
			case 1: $titre .= "différés au ".$this->debut; break;
			case 2: $titre .= "différés non encaissés au ".$this->debut; break;
		}
	}
	
	private function cv($txt) {
		return iconv('UTF-8','ISO-8859-15//TRANSLIT',$txt);
	}

	function Header() {
		$y = 5;
		$this->SetFont('Arial','B',10);
		$this->SetXY($this->left, $y);
		$this->Cell(30, 4, date('d/m/Y H:i'));
		$this->SetXY(-40, $y);
		$this->Cell(35, 4, 'Page '.$this->PageNo(), 0, 0, 'R');
		$y += 5;
		$this->SetFont('Arial','B',12);
		$this->SetXY($this->left, $y);
		$this->Cell(200, 4, $this->cv($this->titre), 0, 0, 'C');
		$y += 8;
		
		$this->SetXY($this->left, $y);
		$this->SetFont('Arial','BU',10);
		$n = count($this->head);
		for($i = 0; $i < $n; $i++)
			$this->Cell($this->width[$i], 5, $this->cv($this->head[$i]), 0, 0, $this->align[$i]);
	
		$this->posy = $y+6;
		$this->SetXY($this->left, $this->posy);
	}

	function Footer() {
		$this->SetXY($this->left, $this->posy);
	}
	
	function PrintTotal() {
		$this->SetFont('Arial','B',10);
		$this->SetXY($this->left, $this->posy+4);
		$this->Cell($this->width[0], 4, '');
		$this->Cell($this->width[1], 4, '');
		$this->Cell($this->width[2], 4, '');
		$this->Cell($this->width[3], 4, $this->totaux[0], 0, 0, $this->align[3]);
		$this->Cell($this->width[4], 4, $this->totaux[1], 0, 0, $this->align[4]);
		$this->Cell($this->width[5], 4, $this->totaux[2], 0, 0, $this->align[5]);
		$this->Cell($this->width[6], 4, '');
		$t = "Total Général : ".($this->totaux[0]+$this->totaux[1]+$this->totaux[2]);
		$this->Cell($this->width[7], 4, $this->cv($t), 0, 0, 'L');
		$this->total = true;
	}
	
	function PrintLines($regl) {
		$this->SetFont('Arial','',10);
		foreach($regl as $r) $this->printLine($r);
	}

	private function printLine($l) {
		$b = '';
		$e = '';
		$c = '';
		$m = (float)$l['Montant'];
		switch($l['ModeReglement']) {
			case 'B': $b = $m; $this->totaux[0] += $m; break;
			case 'E': $e = $m; $this->totaux[1] += $m; break;
			case 'C': $c = $m; $this->totaux[2] += $m; break;
		}
		$this->SetXY($this->left, $this->posy);
		$this->Cell($this->width[0], 4, '');
		$this->Cell($this->width[1], 4, $l['Utilisateur'], 0, 0, $this->align[1]);
		$this->Cell($this->width[2], 4, date('d/m/Y', $l['DateReglement']), 0, 0, $this->align[2]);
		$this->Cell($this->width[3], 4, $b, 0, 0, $this->align[3]);
		$this->Cell($this->width[4], 4, $e, 0, 0, $this->align[4]);
		$this->Cell($this->width[5], 4, $c, 0, 0, $this->align[5]);
		$this->Cell($this->width[6], 4, '');
		$this->Cell($this->width[7], 4, $l['Numero'].'   '.$this->cv($l['Nom'].'  '.$l['Prenom']), 0, 0, $this->align[6]);
		$this->posy += 4;
	} 

}