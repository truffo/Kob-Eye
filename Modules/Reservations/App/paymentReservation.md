[STORPROC Reservations/Reservation/[!Res!]|R|0|1]
    [IF [!R::getTotal()!]>0]
        //génération de la facture et du paiement
        [!Facture:=[!R::getFacture()!]!]
        [!Paiement:=[!Facture::getPaiement()!]!]
        [STORPROC Reservations/TypePaiement/Actif=1|TP]
            [!Plugin:=[!TP::getPlugin()!]!]
            [!Plugin::getCodeHTML([!Paiement!])!]
        [/STORPROC]
    [ELSE]
    [/IF]
[/STORPROC]