define(['custom/config', 'jquery', 'domReady!'], function (config, $) {
    // Some state variables
    return {
        user: undefined,
        loggedIn: undefined,
        csrfToken: undefined,
        currentlyCheckingLogin: false,
        gameAppBaseUrl: $("#gameAppBaseUrl").val(),
        gameApiBaseUrl: $("#gameApiBaseUrl").val(),

        // collections
        games: undefined,
        players: undefined
    };
});