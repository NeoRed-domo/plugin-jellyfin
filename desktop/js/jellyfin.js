/*
 * Gestion du Plugin Jellyfin
 * Auteur : NeoRed
 */

// Fonction de traduction sécurisée
function _t(str) {
    if (typeof jeedom !== 'undefined' && jeedom.ui && jeedom.ui.translate) {
        return jeedom.ui.translate(str);
    }
    return str; // Fallback au français si le traducteur est absent
}

$('.eqLogicAction[data-action=add]').on('click', function () {
    $.ajax({
        type: 'POST',
        url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
        data: { action: 'add' },
        dataType: 'json',
        error: function (request, status, error) { handleAjaxError(request, status, error); },
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            $('#div_alert').showAlert({message: _t('Equipement ajouté avec succès'), level: 'success'});
            window.location.reload();
        }
    });
});

$('.eqLogicAction[data-action=scanClients]').on('click', function () {
    $('#div_alert').showAlert({message: _t('Scan en cours...'), level: 'warning'});
    $.ajax({
        type: 'POST',
        url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
        data: { action: 'scanClients' },
        dataType: 'json',
        error: function (request, status, error) { handleAjaxError(request, status, error); },
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            $('#div_alert').showAlert({message: _t('Scan terminé ! Clients trouvés : ') + data.result, level: 'success'});
            setTimeout(function () { window.location.reload(); }, 1000);
        }
    });
});

$('.eqLogicAction[data-action=gotoPluginConf]').on('click', function () {
    window.location.href = 'index.php?v=d&p=plugin&id=jellyfin';
});


// --- EXPLORATEUR DE BIBLIOTHÈQUE ---

var _savedState = {
    id: null,
    name: "",
    path: [],
    item: null
};

if (typeof JellyfinBrowser !== 'undefined') {
    _savedState.id = JellyfinBrowser.currentEqLogicId;
    _savedState.name = JellyfinBrowser.currentEqLogicName;
    _savedState.path = JellyfinBrowser.currentPath; 
    _savedState.item = JellyfinBrowser.selectedItem; 
}

var JellyfinBrowser = {
    currentEqLogicId: _savedState.id, 
    currentEqLogicName: _savedState.name,
    currentPath: _savedState.path || [], 
    selectedItem: _savedState.item,

    ticksToTime: function(ticks) {
        if (!ticks) return "";
        var totalSeconds = Math.floor(ticks / 10000000);
        var hours = Math.floor(totalSeconds / 3600);
        var minutes = Math.floor((totalSeconds % 3600) / 60);
        var seconds = totalSeconds % 60;
        
        var res = "";
        if (hours > 0) res += hours + "h ";
        if (minutes > 0 || hours > 0) res += (minutes < 10 && hours > 0 ? "0" + minutes : minutes) + "m ";
        res += (seconds < 10 ? "0" + seconds : seconds) + "s";
        return res;
    },

    open: function (eqLogicId, eqLogicName) {
        JellyfinBrowser.currentEqLogicId = eqLogicId;
        JellyfinBrowser.currentEqLogicName = eqLogicName || ""; 
        JellyfinBrowser.currentPath = [];
        JellyfinBrowser.selectedItem = null;

        var titleStr = "<span style='color:#fff;'><i class='fas fa-film'></i> " + _t("Bibliothèque Jellyfin");
        if(JellyfinBrowser.currentEqLogicName !== "") {
            titleStr += " <span style='color:#888; margin:0 5px;'>|</span> <span style='color:#1DB954; font-weight:bold;'>" + JellyfinBrowser.currentEqLogicName + "</span>";
        }
        titleStr += "</span>";

        var myModal = bootbox.dialog({
            title: titleStr,
            message: `
                <div id="jellyfin-browser-container" style="height: 70vh; display: flex; flex-direction: column;">
                    <div id="jellyfin-top-bar" style="padding: 10px; background: #333; border-bottom: 1px solid #444; color: #fff; font-size: 14px; display: flex; align-items: center; justify-content: space-between;">
                        <div id="jellyfin-breadcrumbs" style="flex-grow: 1; margin-right: 10px;">
                            <span class="cursor hover-text" onclick="JellyfinBrowser.loadFolder('')"><i class="fas fa-home"></i> ${_t("Accueil")}</span>
                        </div>
                        <div class="input-group" style="width: 200px;">
                             <input type="text" id="jellyfin-search-input" class="form-control input-sm" placeholder="${_t("Rechercher...")}" style="background: #222; border: 1px solid #444; color: #fff;">
                             <span class="input-group-btn">
                                <button class="btn btn-default btn-sm" type="button" onclick="JellyfinBrowser.search()" style="background: #444; border: 1px solid #444; color: #fff;"><i class="fas fa-search"></i></button>
                             </span>
                        </div>
                    </div>

                    <div id="jellyfin-browser-content" style="flex-grow: 1; overflow-y: auto; padding: 20px; background: #202020; display: flex; flex-wrap: wrap; align-content: flex-start;">
                        <div style="width: 100%; text-align: center; margin-top: 50px; color: #ccc;"><i class="fas fa-spinner fa-spin fa-3x"></i><br>${_t("Chargement...")}</div>
                    </div>
                    <div id="jellyfin-selection-info" style="display:none; padding: 15px; background: #2b2b2b; border-top: 1px solid #444; color: #fff; min-height: 100px;">
                         <div style="display: flex;">
                             <div id="sel-img-container" style="width: 60px; height: 90px; margin-right: 15px; flex-shrink: 0; background: #000; display:none; border-radius:4px; overflow:hidden;">
                                <img id="sel-img" src="" style="width:100%; height:100%; object-fit: cover;">
                             </div>
                             <div style="flex-grow: 1;">
                                 <div style="font-size: 18px; font-weight:bold; color: #1DB954; margin-bottom: 5px;">
                                    <span id="sel-title">${_t("Aucun")}</span>
                                 </div>
                                 <div style="font-size: 13px; color: #aaa; margin-bottom: 8px;">
                                    <span id="sel-year"></span> <span id="sel-duration" style="margin-left:10px; color:#bbb; background:#444; padding:1px 5px; border-radius:3px;"></span>
                                    <span id="sel-rating" style="margin-left:10px; color:#f39c12;"></span>
                                 </div>
                                 <div id="sel-overview" style="font-size: 13px; color: #ccc; line-height: 1.4; max-height: 60px; overflow-y: auto;"></div>
                             </div>
                         </div>
                    </div>
                </div>
                <style>
                    .jellyfin-modal-fullscreen .modal-dialog { width: 90% !important; max-width: 90% !important; margin: 30px auto; }
                    .jellyfin-modal-fullscreen .modal-content { background-color: #202020; border: 1px solid #444; box-shadow: 0 0 20px rgba(0,0,0,0.8); }
                    .jellyfin-modal-fullscreen .modal-header { border-bottom: 1px solid #444; background-color: #1a1a1a; color: white; }
                    .jellyfin-modal-fullscreen .close { color: white; opacity: 0.8; }
                    .jellyfin-modal-fullscreen .modal-footer { border-top: 1px solid #444; background-color: #1a1a1a; }
                    .jelly-card { width: 140px; margin: 10px; cursor: pointer; transition: all 0.2s; display: flex; flex-direction: column; align-items: center; position: relative; }
                    .jelly-card:hover { transform: scale(1.05); z-index: 10; }
                    .jelly-card.selected .jelly-img-container { border: 3px solid #1DB954; box-shadow: 0 0 15px rgba(29, 185, 84, 0.6); }
                    .jelly-img-container { width: 130px; height: 195px; background: #333; border-radius: 6px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.5); position: relative; display: flex; align-items: center; justify-content: center; border: 3px solid transparent; }
                    .jelly-img-container img { width: 100%; height: 100%; object-fit: cover; }
                    .jelly-folder .jelly-img-container { height: 130px; background: #444; }
                    .jelly-title { margin-top: 8px; text-align: center; font-size: 12px; line-height: 1.3; max-height: 32px; overflow: hidden; width: 100%; color: #ddd; text-shadow: 1px 1px 2px black; }
                    .hover-text:hover { color: #1DB954; text-decoration: underline; }
                    #sel-overview::-webkit-scrollbar { width: 4px; }
                    #sel-overview::-webkit-scrollbar-thumb { background: #555; border-radius: 2px; }
                    .btn-favorite { background-color: #c23642 !important; border-color: #c23642 !important; color: white !important; }
                    .btn-favorite:hover { background-color: #d64552 !important; border-color: #d64552 !important; }
                </style>
            `,
            buttons: {
                cancel: { 
                    label: _t("Annuler"), 
                    className: "btn-default pull-left", 
                    callback: function () {} 
                },
                createCmd: {
                    label: "<i class='fas fa-heart'></i> " + _t("Ajouter aux favoris"), 
                    className: "btn-favorite", 
                    callback: function (e) {
                        if (JellyfinBrowser.selectedItem) {
                            var item = JellyfinBrowser.selectedItem;
                            JellyfinBrowser.createCommand(item.Id, item.Name, item.ImgTag, e.target);
                        } else {
                            bootbox.alert(_t("Veuillez sélectionner un média."));
                        }
                        return false; 
                    }
                },
                playNow: {
                    label: "<i class='fas fa-play'></i> " + _t("Lire maintenant"),
                    className: "btn-success",
                    callback: function () {
                        if (JellyfinBrowser.selectedItem) {
                            JellyfinBrowser.playItem(JellyfinBrowser.selectedItem.Id, 'play_now');
                        } else {
                            bootbox.alert(_t("Veuillez sélectionner un média."));
                            return false; 
                        }
                    }
                },
                validate: {
                    label: "OK",
                    className: "btn-primary",
                    callback: function () {}
                }
            },
            className: 'jellyfin-modal-fullscreen'
        });
        
        // GESTION DU CLAVIER (Touche Entrée)
        $('#jellyfin-search-input').on('keypress', function (e) {
            if (e.which === 13) {
                JellyfinBrowser.search();
            }
        });

        $('#jellyfin-selection-info').hide();
        JellyfinBrowser.loadFolder('');
    },

    goBackTo: function(index) {
        var target = JellyfinBrowser.currentPath[index];
        JellyfinBrowser.currentPath = JellyfinBrowser.currentPath.slice(0, index);
        JellyfinBrowser.loadFolder(target.id, target.name);
    },
    
    // NOUVELLE FONCTION DE RECHERCHE
    search: function() {
        var searchTerm = $('#jellyfin-search-input').val();
        if(searchTerm === "") {
             JellyfinBrowser.loadFolder(''); // Si vide, on recharge l'accueil
             return;
        }

        $('#jellyfin-browser-content').html('<div style="width: 100%; text-align: center; margin-top: 50px; color: #ccc;"><i class="fas fa-spinner fa-spin fa-3x"></i><br>' + _t("Recherche...") + '</div>');
        $('#jellyfin-selection-info').hide();
        JellyfinBrowser.selectedItem = null;
        
        // Mise à jour du fil d'ariane pour indiquer la recherche
        var bcHtml = '<span class="cursor hover-text" onclick="JellyfinBrowser.loadFolder(\'\')"><i class="fas fa-home"></i> ' + _t("Accueil") + '</span>';
        bcHtml += ' <span style="color:#888;">&gt;</span> <span class="label label-primary" style="background:#1DB954;">Recherche : ' + searchTerm + '</span>';
        $('#jellyfin-breadcrumbs').html(bcHtml);

        $.ajax({
            type: 'POST',
            url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'getLibrary', search: searchTerm }, // On envoie 'search'
            dataType: 'json',
            success: function (data) {
                if (data.state != 'ok') {
                    $('#jellyfin-browser-content').html('<div class="alert alert-danger">' + data.result + '</div>');
                    return;
                }
                JellyfinBrowser.renderItems(data.result);
            }
        });
    },

    loadFolder: function (parentId, parentName) {
        // Vider le champ de recherche quand on navigue
        $('#jellyfin-search-input').val(''); 

        $('#jellyfin-browser-content').html('<div style="width: 100%; text-align: center; margin-top: 50px; color: #ccc;"><i class="fas fa-spinner fa-spin fa-3x"></i><br>' + _t("Chargement...") + '</div>');
        $('#jellyfin-selection-info').hide();
        JellyfinBrowser.selectedItem = null;

        if (parentId === '') { 
            JellyfinBrowser.currentPath = []; 
        } else if (parentName) { 
            JellyfinBrowser.currentPath.push({id: parentId, name: parentName}); 
        }
        
        var bcHtml = '<span class="cursor hover-text" onclick="JellyfinBrowser.loadFolder(\'\')"><i class="fas fa-home"></i> ' + _t("Accueil") + '</span>';
        
        $.each(JellyfinBrowser.currentPath, function(idx, item){
             bcHtml += ' <span style="color:#888;">&gt;</span> ';
             if (idx === JellyfinBrowser.currentPath.length - 1) {
                 bcHtml += '<span class="label label-default" style="background:#555;">' + item.name + '</span>';
             } else {
                 bcHtml += '<span class="label label-default cursor hover-text" onclick="JellyfinBrowser.goBackTo(' + idx + ')">' + item.name + '</span>';
             }
        });
        $('#jellyfin-breadcrumbs').html(bcHtml);

        $.ajax({
            type: 'POST',
            url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'getLibrary', parentId: parentId },
            dataType: 'json',
            success: function (data) {
                if (data.state != 'ok') {
                    $('#jellyfin-browser-content').html('<div class="alert alert-danger">' + data.result + '</div>');
                    return;
                }
                JellyfinBrowser.renderItems(data.result);
            }
        });
    },

    renderItems: function (result) {
        var container = $('#jellyfin-browser-content');
        container.empty();

        if (!result || !result.Items || result.Items.length === 0) {
            container.html(`
                <div style="width:100%; text-align:center; padding:50px; color: #777; font-size: 1.2em;">
                    <i class="fas fa-network-wired" style="font-size: 30px; margin-bottom: 10px; opacity: 0.5;"></i><br>
                    ${_t("Aucun résultat")}<br>
                    <span style="font-size: 0.8em; color: #666;">(${_t("Ou serveur Jellyfin hors ligne")})</span>
                </div>
            `);
            return;
        }

        $.each(result.Items, function (index, item) {
            var isFolder = item._is_folder; 
            var title = item.Name;
            var imageUrl = item._full_img_url; 
            var imgTag = item._img_tag; 
            var overview = (item.Overview) ? item.Overview.replace(/'/g, "\\'").replace(/"/g, '&quot;') : '';
            var year = (item.ProductionYear) ? item.ProductionYear : '';
            var rating = (item.CommunityRating) ? item.CommunityRating : '';
            var duration = (item.RunTimeTicks) ? JellyfinBrowser.ticksToTime(item.RunTimeTicks) : '';

            var cardHtml = '';
            if (isFolder) {
                var imgContent = (item.ImageTags && item.ImageTags.Primary) ? `<img src="${imageUrl}" loading="lazy">` : `<i class="fas fa-folder" style="font-size: 50px; color: #aaa;"></i>`;
                cardHtml = `<div class="jelly-card jelly-folder" onclick="JellyfinBrowser.loadFolder('${item.Id}', '${title.replace(/'/g, "\\'")}')"><div class="jelly-img-container">${imgContent}</div><div class="jelly-title">${title}</div></div>`;
            } else {
                var imgContent = `<img src="${imageUrl}" loading="lazy" onerror="this.onerror=null;this.parentNode.innerHTML='<i class=\\'fas fa-film\\' style=\\'font-size:40px;color:#555;\\'></i>';">`;
                cardHtml = `<div class="jelly-card jelly-media" id="card-${item.Id}" onclick="JellyfinBrowser.selectMedia('${item.Id}', '${title.replace(/'/g, "\\'")}', '${imgTag}', '${year}', '${rating}', '${overview}', '${imageUrl}', '${duration}')"><div class="jelly-img-container">${imgContent}</div><div class="jelly-title">${title}</div></div>`;
            }
            container.append(cardHtml);
        });
    },

    selectMedia: function (itemId, title, imgTag, year, rating, overview, imgUrl, duration) {
        $('.jelly-card').removeClass('selected');
        $('#card-' + itemId).addClass('selected');
        JellyfinBrowser.selectedItem = {Id: itemId, Name: title, ImgTag: imgTag};
        
        $('#sel-title').text(title);
        $('#sel-year').text(year ? year : '');
        
        if (duration) {
            $('#sel-duration').html('<i class="far fa-clock"></i> ' + duration).show();
        } else {
            $('#sel-duration').hide();
        }

        if (rating) { $('#sel-rating').html('<i class="fas fa-star"></i> ' + rating); } 
        else { $('#sel-rating').html(''); }

        $('#sel-overview').text(overview ? overview : _t("Pas de résumé disponible."));
        if (imgUrl) { $('#sel-img').attr('src', imgUrl); $('#sel-img-container').show(); } 
        else { $('#sel-img-container').hide(); }

        $('#jellyfin-selection-info').fadeIn(200);
    },

    playItem: function (itemId, mode) {
        var btn = $('.btn-success');
        var originalText = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i> ' + _t("Envoi..."));
        $.ajax({
            type: 'POST',
            url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'play_media', id: JellyfinBrowser.currentEqLogicId, mediaId: itemId, mode: mode },
            dataType: 'json',
            success: function (data) {
                if (data.state != 'ok') { bootbox.alert(_t("Erreur : ") + data.result); btn.html(originalText); } 
                else { bootbox.hideAll(); }
            }
        });
    },

    createCommand: function (itemId, name, imgTag, btnElement) {
        var btn = (btnElement) ? $(btnElement).closest('.btn') : $('.jellyfin-modal-fullscreen .modal-footer .btn-info');
        var originalText = "<i class='fas fa-heart'></i> " + _t("Ajouter aux favoris"); 
        btn.html('<i class="fas fa-spinner fa-spin"></i> ' + _t("Ajout..."));
        $.ajax({
            type: 'POST',
            url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'create_command', id: JellyfinBrowser.currentEqLogicId, mediaId: itemId, name: name, imgTag: imgTag },
            dataType: 'json',
            success: function (data) {
                btn.html(originalText);
                if (data.state != 'ok') {
                    if(data.result && data.result.indexOf("existe déjà") !== -1) {
                         var notif = $('<div style="position:fixed; top:20px; right:20px; background:#f39c12; color:white; padding:15px; border-radius:5px; z-index:9999; box-shadow: 0 4px 12px rgba(0,0,0,0.5);">' + _t("Déjà dans les favoris") + '</div>');
                         $('body').append(notif);
                         setTimeout(function(){ notif.fadeOut(500, function(){ $(this).remove(); }); }, 2000);
                    } else { bootbox.alert(_t("Erreur : ") + data.result); }
                } else {
                    var notif = $('<div style="position:fixed; top:20px; right:20px; background:#1DB954; color:white; padding:15px; border-radius:5px; z-index:9999; box-shadow: 0 4px 12px rgba(0,0,0,0.5); font-weight:bold;"><i class="fas fa-check"></i> ' + _t("Favori ajouté !") + '</div>');
                    $('body').append(notif);
                    setTimeout(function(){ notif.fadeOut(500, function(){ $(this).remove(); }); }, 2000);
                }
            }
        });
    }
};