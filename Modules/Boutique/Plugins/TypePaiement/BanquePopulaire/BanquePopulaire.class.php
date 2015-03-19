<?php

/*********************************************
*
* Module de paiement
* Crédit Mutuel
* Abtel
* 
*********************************************/

require_once( dirname(dirname(__FILE__)).'/TypePaiement.interface.php' );

class BoutiqueTypePaiementBanquePopulaire extends Plugin implements BoutiqueTypePaiementPlugin {

	/**
	* initStatePaiement
	* Initiliase le paiement avec ses propriétés particulières
	*/
	public function initStatePaiement() {
		return 0;
	}

	public function __construct() {
		$this->version = "V1";
		$this->currency = "978";
		$this->payment_config = "SINGLE";
	}
	/**
	 * getCodeHTML
	 * return the html code generated by the payment module.
	 * $paiement = Boutique/Paiement Objectclass
	 **/
	public function getCodeHTML( $paiement ) {
		// Params
		$params['site_id'] = $this->Params['IDSITE'];
		$key = $this->Params["CERTIFICAT"];
		$params['ctx_mode'] = $this->Params['MODEPRODUCTION'] == 1 ? "PRODUCTION" : "TEST";
		$params['version'] = $this->version;
		$params['amount'] = round($paiement->Montant * 100); // Attention, il faut fournir le montant en cts
		$params['capture_delay'] = "0";
		$params['currency'] = $this->currency;
		$params['payment_cards'] = "";
		$params['payment_config'] = $this->payment_config;
		$params['validation_mode'] = "";
		$params['trans_id'] = sprintf("%06d", $paiement->Id);
		$params['trans_date'] = gmdate("YmdHis", time());
		$signature_contents = $params['version'] . "+" . $params['site_id'] . "+" . $params['ctx_mode'] . "+"
			. $params['trans_id'] . "+" . $params['trans_date'] . "+" . $params['validation_mode'] . "+"
			. $params['capture_delay'] . "+" . $params['payment_config'] . "+" . $params['payment_cards'] . "+"
			. $params['amount'] . "+" . $params['currency'] . "+" . $key;
		$params['signature'] = sha1($signature_contents);

		// Html
		$html = '<form class="paiement4b" id="PaiementForm" method="POST" action="https://systempay.cyberpluspaiement.com/vads-payment/">';
		foreach($params as $nom => $valeur) $html .= '<input type="hidden" name="'. $nom . '" value="'. $valeur . '" />';
		$html .= "En cliquant sur le bouton ci-dessous, vous allez être redirigé vers l'interface de paiement.<br /><br />";
		$html .= '<input type="submit" name="payer" value="Payer ma commande"/>';
		$html .= '<script>
		// Verification à la soumission du formulaire
		$(\'PaiementForm\').addEvent(\'submit\', function(e) {
			// On execute la requete      
			var r = new Request.JSON({
				url: "/Boutique/Paiement/setPending.json?Paiement=" + '.$paiement->Id.',
				onSuccess: function (json) {
					//validation du formulaire
					new Event(e).start();
				}
			}).send();
  		});
		</script>';

		// Paiement annulé si on ne va pas plus loin
		$paiement->Set('Etat',3);
		$paiement->Save();
		return $html;
	}

	public function serveurAutoResponse( $paiement, $commande ) {
		// Vérification signature
		$signature = sha1(
			$_POST['version'] . "+" . $_POST['site_id'] . "+" . $_POST['ctx_mode'] . "+" . $_POST['trans_id'] . "+" . $_POST['trans_date'] . "+" . 
			$_POST['validation_mode'] . "+" . $_POST['capture_delay'] . "+" . $_POST['payment_config'] . "+" . $_POST['card_brand'] ."+" . 
			$_POST['card_number'] . "+" . $_POST['amount'] . "+" . $_POST['currency'] ."+" . $_POST['auth_mode'] ."+" . $_POST['auth_result'] ."+" .
			$_POST['auth_number'] ."+" . $_POST['warranty_result'] ."+" . $_POST['payment_certificate'] ."+" . $_POST['result'] ."+" . $_POST['hash'] . "+" . $this->Params["CERTIFICAT"]
		);
		if($signature != $_POST['signature']) {
			return false;
		}

		// Retourne le code d'état du paiement
		$etat = ($_POST['result'] == '00') ? 1 : 2;
		return array(
			'etat' => $etat,
			'ref' => $_POST['auth_number']
		);
	}

	public function retrouvePaiementEtape4s() {
		if(isset($_POST['trans_id']) and !empty($_POST['trans_id'])) return round($_POST['trans_id']);
		return false;
	}

	public function affichageEtape5( $paiement, $commande ) {
		if($commande->Paye) return 'Votre commande a été enregistrée sous le numéro '. $commande->RefCommande;
		else return 'Une erreur est survenue lors du paiement de la commande '. $commande->RefCommande . '<br /> Vous pouvez contacter le support via ce <a href="/Contact">formulaire</a> en rappelant cette référence.';
	}

}