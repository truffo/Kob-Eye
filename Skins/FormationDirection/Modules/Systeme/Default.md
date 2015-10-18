[IF [!Sys::User::isRole(GLOBAL)!]]
    [MODULE Systeme/SelectProjet]
[ELSE]
    [!Region:=[!Sys::User::Informations!]!]
    [IF [!Region!]=]
            [DIE]<h1>Erreur de configuration .... Veuillez contacter l'administrateur</h1>[/DIE]
    [/IF]

    [IF [!Sys::User::isRole(REGION)!]]
        [IF [!CurrentRegion!]=]
            [COOKIE Set|CurrentRegion|Region]
            [REDIRECT][/REDIRECT]
        [ELSE]
            [MODULE Systeme/SelectProjetRegion]
        [/IF]
    [/IF]
    [IF [!Sys::User::isRole(INTER-REGION)!]]
        [IF [!CurrentRegion!]=]
            [COOKIE Set|CurrentRegion|Region]
            [REDIRECT][/REDIRECT]
        [ELSE]
            [MODULE Systeme/SelectProjetInterRegion]
        [/IF]
    [/IF]
[/IF]