[INFO [!Query!]|I]
[IF [!I::TypeSearch!]=Direct]
    [STORPROC [!Query!]|O|0|1][/STORPROC]
[ELSE]
    //nouveau
    [OBJ [!I::Module!]|[!I::ObjectType!]|O]
    [METHOD O|AddParent][PARAM][!I::LastDirect!][/PARAM][/METHOD]
[/IF]
[MODULE Systeme/Utils/Form]