{
    "success": true,
    "data": [
        [STORPROC TennisForever/TypeCourt/Web=1|C|0|100]
            [IF [!Pos!]>1],[/IF]{
                "Id":[!C::Id!],
                "Titre": "[!C::Titre!]",
                "Type": "[!C::Reservation!]"
            }
        [/STORPROC]
    ]
}
