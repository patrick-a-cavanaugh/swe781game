requirejs.config({
    //By default load any module IDs from js/vendor
    baseUrl: '/js/vendor',
    //except, if the module ID starts with "custom",
    //load it from the js/app directory. paths
    //config is relative to the baseUrl, and
    //never includes a ".js" extension since
    //the paths config could be for a directory.
    paths: {
        bootstrap: 'bootstrap.min',
        custom: '../custom',
        domReady: '../domReady',
        text: '../text',
        jquery: 'jquery-1.8.0.min',
        modernizr: 'modernizr-2.6.1.min'
    },
    shim: {
        modernizr: {
            exports: 'Modernizr'
        },
        bootstrap: {
            deps: ['jquery']
        },
        'jquery.cookie': {
            deps: ['jquery']
        }
    }
});

requirejs([
    // We use these libraries so they come first to become params.
    'jquery', 'underscore', 'backbone', 'humps', 'custom/loginServices', 'custom/gameApp',
    'custom/gameViews', 'custom/gameModels', 'custom/gameRouters',
    // These we just want to load
    'custom/plugins', 'custom/jqueryPlugins', 'bootstrap', 'jquery.cookie', 'domReady!'],
    function ($, _, Backbone, humps, LoginServices, gameApp, GameViews, GameModels, GameRouters) {

        var flashMessage = $.cookie('flash');
        if (flashMessage) {
            console.log("Flash message found.");
            $.removeCookie('flash');
            var flashMessageAlert = $("<div></div>").addClass('alert');
            flashMessageAlert.append($('<button type="button" class="close" data-dismiss="alert">&times;</button>'));
            flashMessageAlert.append($("<span></span>").text(flashMessage));
            $("#flashMessage").append(flashMessageAlert);
            setTimeout(function () {
                $("#flashMessage").empty();
            }, 7500);
        }

        gameApp.currentlyCheckingLogin = true;
        gameApp.checkForLogin = function () {
            LoginServices.checkLogin(function (userName, newLoginFormHtml) {
                // This login check also results in getting the needed CSRF token for further requests.
                console.log("login check is completed", userName, newLoginFormHtml);

                gameApp.currentlyCheckingLogin = false;
                if (newLoginFormHtml) {
                    gameApp.loginFormHtml = newLoginFormHtml;
                }
            });
        };
        gameApp.loginFormHtml = null;

        // Set up an ajax event to include the API token with every jQuery request automatically.
        var bodyEl = $("body");
        bodyEl.ajaxSend(function (sentEvent, jqXhr, settings) {
            if (settings.url.indexOf(gameApp.gameAppBaseUrl) === 0) {
                console.log("AJAX request being made to " + gameApp.gameAppBaseUrl + " - adding CSRF token");
                jqXhr.setRequestHeader("X-GameApp-CSRF-Token", gameApp.csrfToken);
            }
        });
        // Just a debugging function for local development. This won't have an effect in production.
        bodyEl.ajaxError(function (errorEvent, jqXhr, settings, thrownError) {
            console.log("in ajaxError handler");
            if (jqXhr.responseText.indexOf("sf-exceptionreset") >= 0 && jqXhr.status >= 500) {
                var sfExceptionText = jqXhr.responseText.replace(/^[\s\S]*<body.*?>|<\/body>[\s\S]*$/g, '');
                var sfException = $("<div>" + sfExceptionText + "</div>").find(".sf-exceptionreset");
                $("body").empty().append(sfException);
            }
        });

        console.log("route: index");
        var waitForLoginCheck = function () {
            if (!gameApp.currentlyCheckingLogin) {
                // Don't start the router until we know info like the user id.
                var router = new GameRouters.GameAppRouter({
                    "appView": new GameViews.AppView()
                });
                Backbone.emulateHTTP = true;
                Backbone.history.start({pushState: true});

                var currentRoute = Backbone.history.fragment;
                var currentRouteIsAuthenticatedArea = true;
                if (currentRoute === 'gameApp/register' || currentRoute === 'gameApp/login' || currentRoute === '') {
                    currentRouteIsAuthenticatedArea = false;
                }
                console.log("currentRoute", currentRoute, "currentRouteIsAuthenticatedArea",
                    currentRouteIsAuthenticatedArea, "loginFormHtml", gameApp.loginFormHtml);
                if (!currentRouteIsAuthenticatedArea && gameApp.user && gameApp.user.username) {
                    Backbone.history.navigate("gameApp/userHome", {trigger: true});
                } else if (currentRouteIsAuthenticatedArea && !gameApp.user) {
                    Backbone.history.navigate("", {trigger: true});
                }

                clearInterval(intervalId);
            }
        };
        gameApp.checkForLogin();
        var intervalId = setInterval(waitForLoginCheck, 100);

        // This link handling code is taken from Backbone Boilerplate and slightly modified

        // All navigation that is relative should be passed through the navigate
        // method, to be processed by the router.  If the link has a data-bypass
        // attribute, bypass the delegation completely.
        $(document).on("click", "a:not([data-bypass])", function (evt) {
            // Get the anchor href and protcol
            var href = $(this).attr("href");
            var protocol = this.protocol + "//";

            // Ensure the protocol is not part of URL, meaning its relative.
            if (href && href.slice(0, protocol.length) !== protocol &&
                href.indexOf("javascript:") !== 0 && href.indexOf("/api") !== 0) {
                // Stop the default event to ensure the link will not cause a page
                // refresh.
                evt.preventDefault();

                // `Backbone.history.navigate` is sufficient for all Routers and will
                // trigger the correct events.  The Router's internal `navigate` method
                // calls this anyways.
                Backbone.history.navigate(href, true);
            }
        });

        // Let us view the gameApp globally
        window.gameApp = gameApp;
    });