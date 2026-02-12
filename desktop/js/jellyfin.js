/* Gestion du clic sur le bouton Ajouter */
$('.eqLogicAction[data-action=add]').on('click', function () {
    $.ajax({
        type: 'POST',
        url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
        data: {
            action: 'add',
        },
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            $('#div_alert').showAlert({message: 'Equipement ajouté avec succès', level: 'success'});
            // On recharge la liste ou on va sur la fiche
            window.location.reload();
        }
    });
});

/* NOUVEAU : Gestion du clic sur le bouton Scan */
$('.eqLogicAction[data-action=scanClients]').on('click', function () {
    $('#div_alert').showAlert({message: 'Scan en cours...', level: 'warning'});
    $.ajax({
        type: 'POST',
        url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
        data: {
            action: 'scanClients', // Cette action doit exister dans le fichier AJAX
        },
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            $('#div_alert').showAlert({message: 'Scan terminé ! Clients trouvés : ' + data.result, level: 'success'});
            setTimeout(function () {
                window.location.reload();
            }, 1000);
        }
    });
});

/* Redirection vers la configuration du plugin */
$('.eqLogicAction[data-action=gotoPluginConf]').on('click', function () {
    window.location.href = 'index.php?v=d&p=plugin&id=jellyfin';
});