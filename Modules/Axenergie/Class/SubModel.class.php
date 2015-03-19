<?php
class SubModel extends genericClass {
	
	function Save() {
		$sr = $this->getParents('SubRange');
		if(sizeof($sr)) {
			$this->resetParents('SubRange');
			$sr = $sr[0];
			$mo = $this->getParents('Modele');
			if(!sizeof($mo)) return $this->Delete();
			$pr = $mo[0]->getParents('Produit');
			$pr = $pr[0];
			$sp = Sys::getData('Axenergie','SubRange/'.$sr->Id.'/SubProduct/Produit.ProduitId('.$pr->Id.')');
			if(sizeof($sp)) $sp = $sp[0];
			else {
				$sp = genericClass::createInstance('Axenergie', 'SubProduct');
				$sp->addParent($sr);
				$sp->addParent($pr);
				$sp->Nom = $pr->Nom;
				$sp->Save();
			}
			$this->addParent($sp);
		}
		if(! $this->PrixAdherent) $this->PrixAdherent = $this->PrixHT;
		parent::Save();
	}
	

	
	/**
	 * initFromObject
	 * Initialisation d'un subproduct depuis unproduit
	 */
	function initFromObject($obj) {
		$this->Nom = $obj->Nom;
		$this->Description = $obj->Description;
		$this->Version = $obj->Version;
		$this->PrixAdherent = $this->PrixHT = $obj->PrixHT;
		$this->ImageCatalogue = $obj->ImageCatalogue;
	}
	function Delete() {
		$p = $this->getParents("Modele");
		return Array(Array("delete",parent::Delete(),$this->Id,$this->Module,$this->ObjectType,null,null,null,null,null,Array("ModeleId"=>$p[0]->Id)));
	}
	
	static function add($db,$target,$args,$recurs=false){		//verification de l'existence
		$c2 = SubModel::search($db,$target);
		if (is_object($c2)){
			//l'objet existe deja on le met à jour
			$c2->initFromObject($target);
			$c2->Save();
			return Array(Array("edit",true,$c2->Id,$c2->Module,$c2->ObjectType,null,null,null,null,null,Array("ModeleId"=>$target->Id)));
		}else{
			//Creation de SubModel
			$sr = genericClass::createInstance("Axenergie","SubModel");
			//Recuperation de l'objet parent
			$p = $target->getParents("Produit");
			if (isset($p[0])&&is_object($p[0])){
				$p = $p[0];
				$p2 = $p->getParents("Categorie");
				if (isset($p2[0])&&is_object($p2[0])){
					$p2 = $p2[0];
					$c2 = Sys::$Modules["Axenergie"]->callData("Database/".$db->Id."/SubRange/*/Categorie.CategorieId(".$p2->Id.")");
					$GLOBALS["Systeme"]->Log->log("ADD SUB MODEL ");
					if (isset($c2[0])&&is_array($c2[0])){
						$c2 = genericClass::createInstance('Axenergie',$c2[0]);
						$c = Sys::$Modules["Axenergie"]->callData("SubRange/".$c2->Id."/SubProduct/Produit.ProduitId(".$p->Id.")");
						$GLOBALS["Systeme"]->Log->log("ADD SUB MODEL 2");
						if (isset($c[0])&&is_array($c[0])){
							$c = genericClass::createInstance('Axenergie',$c[0]);
							$sr->AddParent($c);
						}else return SubModel::checkParentNode($db,$target,$args,$recurs);
					}else return SubModel::checkParentNode($db,$target,$args,$recurs);
				}else return "PRODUIT PARENT INTROUVABLE SECOND LEVEL ";
			}else return "PRODUIT PARENT INTROUVABLE SECOND LEVEL ";
			$sr->initFromObject($target);
			$sr->AddParent($target);
			$sr->Save();
			return Array(Array("add",true,$sr->Id,$sr->Module,$sr->ObjectType,$c->ObjectType,$c->Id,null,null,null,Array("ModeleId"=>$target->Id)));
		}
	}

	static function search($db,$target){
		$prod = $target->getParents("Produit");
		$cat = $prod[0]->getParents("Categorie");
		if (isset($cat[0])&&is_object($cat[0])){
			$c = Sys::$Modules["Axenergie"]->callData("Database/".$db->Id."/SubRange/*/Categorie.CategorieId(".$cat[0]->Id.")");
			if (sizeof($c)){
				$c2 = Sys::$Modules["Axenergie"]->callData("SubRange/".$c[0]["Id"]."/SubProduct/Produit.ProduitId(".$prod[0]->Id.")");
				if (sizeof($c2)){
					$c3 = Sys::$Modules["Axenergie"]->callData("SubProduct/".$c2[0]["Id"]."/SubModel/Modele.ModeleId(".$target->Id.")");
					return genericClass::createInstance("Axenergie",$c3[0]);
				}
				return false;
			}
			return false;
		}
		return false;
	}
	/**
	 * update
	 * mise à jour du modele
	 */
	function update(){
		//recupération du modele référent
		$p = $this->getParents("Modele");
		if (!is_object($p[0]))
			return "Cant find the original model. Is this a orphan sub model ?";
		$this->initFromObject($p[0]);
		$this->Save();
		return WebService::WSStatusMulti(Array(Array("edit",true,$this->Id,$this->Module,$this->ObjectType,null,null,null,null,null,Array("ModeleId"=>$p[0]->Id))));
	}
	
	/**
	 * checkParentNode
	 *
	 * Remonte recursivement pour créer les range manquantes
	 */
	static function checkParentNode($db,$target,$args,$recurs=false){
		$GLOBALS["Systeme"]->Log->log("CHECK PARENT NODE FROM MODEL");
		//Récupération du parent
		$p = $target->getParents("Produit");
		//Crée le parent
		$status = SubProduct::add ($db,$p[0],$args,false);
		//Recréation du courant
		$status = array_merge($status,SubModel::add($db,$target,$args,$recurs));
		return $status;
	}
	static function remove($db,$target){
		$pr = $target->getParents("Produit");
		$pr = $pr[0];
		$sp = Sys::$Modules["Axenergie"]->callData("Database/".$db->Id."/SubRange/*/SubProduct/Produit.ProduitId(".$pr->Id.")");
		$sp = genericClass::createInstance("Axenergie",$sp[0]);
		$O = Sys::$Modules["Axenergie"]->callData("SubProduct/".$sp->Id."/SubModel/Modele.ModeleId(".$target->Id.")");
		$GLOBALS["Systeme"]->Log->log("REMOVE SUBMODEL ");
		if (is_array($O))foreach ($O as $s){
			$s = genericClass::createInstance("Axenergie",$s);
			$status = $s->Delete();
		}else return "IMPOSSIBLE DE TROUVER LE SUBMODEL";
		return $status;
	}
	
	function getImage() {
		$mod = $this->getParents('Modele');
		$mod = $mod[0];
		return $mod->getImage($this, $mod);
	}
}
