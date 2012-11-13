define(['jquery', 'custom/config', 'custom/gameApp'], function ($, config, gameApp) {
    return {
        checkLogin: function (callbackFunc) {
            $.get(config.wsUrl + "/", function (data, textStatus, jqXhr) {
                gameApp.csrfToken = jqXhr.getResponseHeader('x-gameapp-csrf-token');
                if (typeof data === 'object' && data.currentUser) {
                    gameApp.loggedIn = true;
                    gameApp.user = {
                        "username": data.currentUser,
                        "id": data.userId
                    };
                    callbackFunc(data.currentUser);
                } else {
                    gameApp.loggedIn = false;
                    gameApp.user = null;
                    callbackFunc(null, data);
                }
            })
        }
    }
});