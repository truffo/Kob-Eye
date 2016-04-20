<?php
class Service extends genericClass {

    function getTarif($ab,$deb,$fin){
        if ($ab) return $this->TarifAbonnes;
        //test heure pleine
        $hc = $this->isHeurePleine($deb,$fin);
        if (!$hc) return $this->TarifCreuse;
        else return $this->Tarif;

    }

    function isHeurePleine($deb,$fin) {
        $heuredeb = (int)date('H',$deb);
        $heurefin = (int)date('H',$fin);
        //créneau 12-14
        if (($heuredeb<14&&$heuredeb>=12)
            || ($heurefin<=14&&$heurefin>12)) return true;

        //créneau 18-21
        if (($heuredeb<21&&$heuredeb>=18)
            || ($heurefin<=21&&$heurefin>18)) return true;

        //week end
        $date = date("l", $deb);
        $date = strtolower($date);
        if ($date == "saturday" || $date == "sunday") return "true";

        // jours fériés
        $jour = date('d',$deb);
        $mois = date('m',$deb);

        //01/01
        if ($jour=="01"&&$mois=="01") return true;
        //01/05
        if ($jour=="01"&&$mois=="05") return true;
        //08/05
        if ($jour=="08"&&$mois=="05") return true;
        //14/07
        if ($jour=="14"&&$mois=="07") return true;
        //15/08
        if ($jour=="15"&&$mois=="08") return true;
        //01/11
        if ($jour=="01"&&$mois=="11") return true;
        //11/11
        if ($jour=="11"&&$mois=="11") return true;
        //25/12
        if ($jour=="25"&&$mois=="12") return true;

        return false;
    }
}