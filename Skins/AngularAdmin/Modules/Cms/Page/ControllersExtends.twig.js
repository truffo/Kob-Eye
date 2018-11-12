app.controller('{{ identifier }}FicheCtrlExtends', function($location, $compile, $sce, $scope, $rootScope, $routeParams,$http,{{ identifier }}Store) {
    if($scope.obj){
        var parser = new DOMParser();

        $scope.tempHtml = parser.parseFromString($scope.obj.HtmlConfig,"text/html");
        $scope.tempConfig = parser.parseFromString($scope.obj.TemplateConfig,"text/xml");
        $scope.ui = '';

        //Init du compte des zones
        $scope.maxZone = 0;
        var zones = $scope.tempConfig.getElementsByTagName('ZONE');
        for(var n in zones){
            if(zones[n] instanceof Element){
                var zid = zones[n].getAttribute('tag');
                zid = zid.split('_')[1];
                if($scope.maxZone < zid)
                    $scope.maxZone = zid;
            }
        }

        //Init du compte des row
        $scope.maxRow = 0;
        var rows = $scope.tempHtml.querySelectorAll('[data-row]');
        for(var n in rows){
            if(rows[n] instanceof HTMLDivElement && $scope.maxRow < rows[n].getAttribute('data-row'))
                $scope.maxRow = rows[n].getAttribute('data-row')
        }


        $scope.$watch(function($scope){
            return $scope.obj.HtmlConfig;
        },function(){
            $scope.updateInterface();
        });
    }

    /////Gestion des lignes
    $scope.createRow = function(parent,order){
        $scope.maxRow++;
        $scope.maxZone++;
        var newDiv = '<div data-row="'+ $scope.maxRow +'" data-order="'+order+'" class="row cms-row"> \
                            <div data-col="'+$scope.maxZone+'" data-order="1" class="cms-col" data-zone="ZONE_'+$scope.maxZone+'">[ZONE_'+$scope.maxZone+']</div> \
                      </div>';
        //transformation string en node html
        var div = document.createElement('div');
        div.innerHTML = newDiv;
        newDiv = div.firstChild;
        var current = $scope.tempHtml.querySelectorAll('[data-order="'+order+'"]');
        if(!parent){
            var parent = $scope.tempHtml.querySelectorAll('[data-dv]')[0];
        } else {
            var parent = $scope.tempHtml.querySelectorAll('[data-dv="'+parent+'"]')[0];
        }
        if(current.length){
            parent.insertBefore(newDiv,current[0]);

        }else{
            parent.insertBefore(newDiv,null);
        }

        $scope.reorder();
        $scope.obj.HtmlConfig = $scope.tempHtml.querySelectorAll('body')[0].innerHTML;

        $scope.maxZone++;
        var parser = new DOMParser();
        var newZone = parser.parseFromString('<TEMP><ZONE tag="ZONE_'+$scope.maxZone+'"> </ZONE></TEMP>',"text/xml");
        newZone =  newZone.getElementsByTagName('ZONE')[0];

        var parent = $scope.tempConfig.getElementsByTagName('ZONES')[0];
        parent.insertBefore(newZone,null);
        $scope.obj.TemplateConfig = $scope.tempConfig.documentElement.outerHTML;
    };
    $scope.moveRow = function(rowId,order,dvId){
        var row = $scope.tempHtml.querySelectorAll('[data-row="'+rowId+'"]')[0];
        var current = $scope.tempHtml.querySelectorAll('[data-order="'+order+'"]');
        if(!parent){
            return false
        } else {
            var parent = $scope.tempHtml.querySelectorAll('[data-dv="'+dvId+'"]')[0];
        }
        if(current.length){
            parent.insertBefore(row,current[0]);
        }else{
            parent.insertBefore(row,null);
        }
        $scope.reorder();
        $scope.obj.HtmlConfig = $scope.tempHtml.querySelectorAll('body')[0].innerHTML;
    };
    $scope.delRow = function(rowId){
        swal({
                title: "Êtes vous sûr de vouloir supprimer cette section ?",
                text: "Cette suppression sera définitive, et elle entraînera la suppression de tout les composants affectés à cette section. ",
                type: "warning",
                showCancelButton: true,
                confirmButtonClass: "btn-danger",
                cancelButtonText: "Annuler",
                confirmButtonText: "Oui, supprimer !",
                closeOnConfirm: true
            },
            function(){
                var row = $scope.tempHtml.querySelectorAll('[data-row="'+rowId+'"]')[0];
                var cols = row.querySelectorAll('[data-col]');

                for(var n in cols){
                    if( !(cols[n] instanceof HTMLDivElement)) continue;

                    var zone = cols[n].getAttribute('data-zone');

                    $scope.tempConfig.querySelectorAll('[tag='+zone+']')[0].remove();
                    $scope.obj.TemplateConfig = $scope.tempConfig.documentElement.outerHTML;
                }
                row.remove();
                $scope.reorder();
                $scope.obj.HtmlConfig = $scope.tempHtml.querySelectorAll('body')[0].innerHTML;
                $scope.$apply();
            });
    };

    /////Gestion des colonnes
    $scope.createCol = function(rowId,order){
        $scope.maxZone++;
        var row = $scope.tempHtml.querySelectorAll('[data-row="'+rowId+'"]')[0];
        var cols = row.querySelectorAll('[data-col]');
        var colWidth = $scope.recalc(rowId);
        var newDiv = '<div data-col="'+$scope.maxZone+'" data-order="1" class="cms-col" data-zone="ZONE_'+$scope.maxZone+'" data-width="'+colWidth+'" style="width: '+colWidth+'%;">[ZONE_'+$scope.maxZone+']</div>';
        //transformation string en node html
        var div = document.createElement('div');
        div.innerHTML = newDiv;
        newDiv = div.firstChild;

        var current = $scope.tempHtml.querySelectorAll('[data-order="'+order+'"]');
        if(current.length){
            row.insertBefore(newDiv,current[0]);
        }else{
            row.insertBefore(newDiv,null);
        }


        $scope.reorder(rowId);
        $scope.obj.HtmlConfig = $scope.tempHtml.querySelectorAll('body')[0].innerHTML;

        var parser = new DOMParser();
        var newZone = parser.parseFromString('<TEMP><ZONE tag="ZONE_'+$scope.maxZone+'"> </ZONE></TEMP>',"text/xml");
        newZone =  newZone.getElementsByTagName('ZONE')[0];

        var parent = $scope.tempConfig.getElementsByTagName('ZONES')[0];
        parent.insertBefore(newZone,null);
        $scope.obj.TemplateConfig = $scope.tempConfig.documentElement.outerHTML;
    };
    $scope.moveCol = function(colId,order,rowId){
        var col = $scope.tempHtml.querySelectorAll('[data-col="'+colId+'"]')[0];
        var row = $scope.tempHtml.querySelectorAll('[data-row="'+rowId+'"]')[0];
        if(!rowId){
            return false;
        } else {
            var parent = $scope.tempHtml.querySelectorAll('[data-row="'+rowId+'"]')[0];
        }
        var current = $scope.tempHtml.querySelectorAll('[data-order="'+order+'"]');
        if(current.length){
            parent.insertBefore(row,current[0]);
        }else{
            parent.insertBefore(row,null);
        }
        $scope.reorder();
        $scope.obj.HtmlConfig = $scope.tempHtml.querySelectorAll('body')[0].innerHTML;
    };
    $scope.delCol = function(colId){
        swal({
                title: "Êtes vous sûr de vouloir supprimer ce composant ?",
                text: "Cette suppression sera définitive et elle entraînera la perte de la configuration qui lui était liée.",
                type: "warning",
                showCancelButton: true,
                confirmButtonClass: "btn-danger",
                cancelButtonText: "Annuler",
                confirmButtonText: "Oui, supprimer !",
                closeOnConfirm: true
            },
            function(){
                var col = $scope.tempHtml.querySelectorAll('[data-col="'+colId+'"]')[0];
                var row = col.parentNode;

                col.remove();
                $scope.reorder(row.getAttribute('data-row'));
                $scope.obj.HtmlConfig = $scope.tempHtml.querySelectorAll('body')[0].innerHTML;

                var zone = col.getAttribute('data-zone');

                $scope.tempConfig.querySelectorAll('[tag='+zone+']')[0].remove();
                $scope.obj.TemplateConfig = $scope.tempConfig.documentElement.outerHTML;
                $scope.$apply();
            });
    };

    //Gestion de composants
    $scope.addComponent = function(zone){

    };
    $scope.moveComponent = function(){

    };
    $scope.delComponent = function(){

    };

    //Reorganisation des  rows/cols
    $scope.reorder = function(rowId){
        if(rowId){
            var row = $scope.tempHtml.querySelectorAll('[data-row="'+rowId+'"]');
            var cols = row[0].querySelectorAll('[data-col]');
            var colOrder = 1;
            for(var n in cols){
                if(cols[n] instanceof HTMLDivElement){
                    cols[n].setAttribute('data-order',colOrder);
                    colOrder++;
                }
            }
        }else{
            var rows = $scope.tempHtml.querySelectorAll('[data-row]');
            var rowOrder = 1;
            for(var n in rows){
                if(rows[n] instanceof HTMLDivElement){
                    rows[n].setAttribute('data-order',rowOrder);
                    rowOrder++;
                }
            }
        }
    };
    //Recalcul de largeurs en gardant les ratios des premieres colonnes
    $scope.recalc = function(rowId){
        var row = $scope.tempHtml.querySelectorAll('[data-row="'+rowId+'"]')[0];
        var cols = row.querySelectorAll('[data-col]');
        var nbCols = parseInt(cols.length) + 1;
        var newColWidth = 100/nbCols;
        var spare = (100-newColWidth)/100;
        var used = 0;
        for(var n in cols){
            if(cols[n] instanceof HTMLDivElement){
                var oldWidth = cols[n].getAttribute('data-width');
                var newWidth = parseInt(oldWidth*spare);
                used += newWidth;
                cols[n].setAttribute('data-width',newWidth);
                cols[n].setAttribute('style','width:'+newWidth+'%;');
            }
        }
        newColWidth = 100 - used ;
        return newColWidth;
    };

    //Generation de l'interface utilisateur
    $scope.updateInterface = function(){
        var base = angular.copy($scope.tempHtml);
        var body = base.querySelectorAll('body')[0];
        var dvContainers = base.querySelectorAll('[data-dv]');
        var rows = base.querySelectorAll('[data-row]');
        var cols = base.querySelectorAll('[data-col]');

        //Ajout du titre
        var newDiv = '<div class="row pageHeader" ng-bind-html="obj.Titre"></div>';
        //transformation string en node html
        var div = document.createElement('div');
        div.innerHTML = newDiv;
        newDiv = div.firstChild;
        body.insertBefore(newDiv,body.firstChild);


        //Ajout des boutons pour créer des rows/sauvegarder...
        var newDiv = '<div class="row pageFooter">  \
                            <button type="button" class="btn btn-success btn-right"  ng-click="pageSave();">Enregistrer</button> \
                            <button type="button" class="btn btn-warning btn-right"  ng-click="pageReinit();">Annuler</button> \
                        </div>';
        //transformation string en node html
        var div = document.createElement('div');
        div.innerHTML = newDiv;
        newDiv = div.firstChild;
        body.insertBefore(newDiv,null);

        for(n in dvContainers){
            if(!(dvContainers[n] instanceof HTMLDivElement))
                continue;

            var newDiv = '<div class="row addRow">  \
                <button type="button" class="btn btn btn-primary"  ng-click="createRow('+dvContainers[n].getAttribute('data-dv')+')">Ajouter une section</button> \
            </div>';
            //transformation string en node html
            var div = document.createElement('div');
            div.innerHTML = newDiv;
            newDiv = div.firstChild;
            dvContainers[n].insertBefore(newDiv,null);
            dvContainers[n].setAttribute('ng-drop','true');
            dvContainers[n].setAttribute('data-drop','col');
            dvContainers[n].setAttribute('ng-drop-success','dropRow($data,$event,$target,$source)');
        }

        //Ajout de boutons pour supprimer des rows / ajouter des colonnes / Mise en place du dragndrop
        for(n in rows){
            if(!(rows[n] instanceof HTMLDivElement))
                continue;

            var newDiv = '<div class="addCol">  \
                            <button type="button" class="btn btn btn-primary"  ng-click="createCol('+rows[n].getAttribute('data-row')+')">Ajouter un composant</button> \
                        </div>';
            //transformation string en node html
            var div = document.createElement('div');
            div.innerHTML = newDiv;
            newDiv = div.firstChild;
            rows[n].insertBefore(newDiv,null);

            var newDiv = '<div class="delRow">  \
                            <button type="button" class="btn btn btn-danger"  ng-click="delRow('+rows[n].getAttribute('data-row')+')">Supprimer la section</button> \
                        </div>';
            //transformation string en node html
            var div = document.createElement('div');
            div.innerHTML = newDiv;
            newDiv = div.firstChild;
            rows[n].insertBefore(newDiv,null);

            var newDiv = '<div class="dragHandle" ng-drag-handle></div>';
            //transformation string en node html
            var div = document.createElement('div');
            div.innerHTML = newDiv;
            newDiv = div.firstChild;
            rows[n].insertBefore(newDiv,null);

            rows[n].setAttribute('ng-drag','true');
            rows[n].setAttribute('ng-drop','true');
            rows[n].setAttribute('data-drop','col');
            rows[n].setAttribute('ng-drop-success','dropCol($data,$event,$target,$source)');


        }


        //Ajout de boutons pour supprimer des colonnes / choisir des composants
        for(n in cols){
            if(!(cols[n] instanceof HTMLDivElement)) continue;


            var newDiv = '<div class="configComp">  \
                            <button type="button" class="btn btn btn-warning"  ng-click="configureComponent('+cols[n].getAttribute('data-col')+')">Configurer le composant</button> \
                        </div>';
            //transformation string en node html
            var div = document.createElement('div');
            div.innerHTML = newDiv;
            newDiv = div.firstChild;
            cols[n].insertBefore(newDiv,null);

            var newDiv = '<div class="delCol">  \
                                <button type="button" class="btn btn btn-danger"  ng-click="delCol('+cols[n].getAttribute('data-col')+')">Supprimer le composant</button> \
                            </div>';
            //transformation string en node html
            var div = document.createElement('div');
            div.innerHTML = newDiv;
            newDiv = div.firstChild;
            cols[n].insertBefore(newDiv,null);

            var newDiv = '<div class="dragHandle" ng-drag-handle></div>';
            //transformation string en node html
            var div = document.createElement('div');
            div.innerHTML = newDiv;
            newDiv = div.firstChild;
            cols[n].insertBefore(newDiv,null);

            cols[n].setAttribute('ng-drag','true');

            console.log(cols[n],cols[n].nextSibling);
        }

        $scope.ui= base.querySelectorAll('body')[0].innerHTML;


    };

    $scope.dropRow = function(data,evt,target,source){
        console.log('dropRow',data,evt,target,source);
    };
    $scope.dropCol = function(data,evt,target,source){
        console.log('dropCol',data,evt,target,source);
    };


    //Save ou reinit ou suivant/precedant
    $scope.pageSave = function(){
        console.log('saveeeeeee');
    };

    $scope.pageReinit = function(){
        console.log('reinittttttttt');
    };

});

app.directive('compileBind', ['$compile', function ($compile) {
    return function(scope, element, attrs) {
        scope.$watch(
            function(scope) {
                // watch the 'compile' expression for changes
                return scope.$eval(attrs.compileBind);
            },
            function(value) {
                // when the 'compile' expression changes
                // assign it into the current DOM
                element.html(value);

                // compile the new DOM and link it to the current
                // scope.
                // NOTE: we only compile .childNodes so that
                // we don't get into infinite loop compiling ourselves
                $compile(element.contents())(scope);
            }
        );
    };
}]);