<?php
class WebSection extends genericClass {
	
	function Delete() {
		$rec = $this->getChildren('WebDiscipline');
		if(count($rec)) throw new Exception('Cette section ne peut être supprimée');

		return parent::Delete();
	}

	
}
