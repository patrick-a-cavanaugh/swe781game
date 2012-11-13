define(['backbone', 'jquery', 'custom/gameApp', 'custom/gameViews', 'custom/gameModels'],
function (Backbone, $, gameApp, GameViews, GameModels) {
    var GameRouters = {};

    gameApp.games = new GameModels.GameList();
    gameApp.players = new GameModels.GamePlayerList();

    GameRouters.GameAppRouter = Backbone.Router.extend({
        routes: {
            "": "index",
            "gameApp/register": "register",
            "gameApp/login": "index",
            "gameApp/userHome": "userHome",
            "gameApp/logout": "logout",
            "gameApp/game/:gameId": "gameHome"
        },

        initialize: function (options) {
            this.appView = options.appView;
        },

        index: function () {
            var self = this;
            console.log("route: index");
            self.appView.showView(new GameViews.LoginForm({
                loginFormHtml: gameApp.loginFormHtml
            }));
        },

        logout: function () {
            console.log("route: logout");
            var waitForCsrfToken = function () {
                if (gameApp.csrfToken) {
                    $.get(gameApp.gameApiBaseUrl + "/logout", {
                        '_csrf_token': gameApp.csrfToken
                    }, function () {
                        console.log("Logged out, now navigating to gameApp/login");
                        gameApp.loggedIn = false;
                        gameApp.loginFormHtml = null;
                        // Now we need the login form HTML again
                        gameApp.checkForLogin();
                        var loginFormWaitInterval = setInterval(function () {
                            if (gameApp.loginFormHtml) {
                                clearInterval(loginFormWaitInterval);
                                Backbone.history.navigate('gameApp/login', {trigger: true});
                            }
                        }, 250);
                    });
                    clearInterval(intervalId);
                }
            };
            var intervalId = setInterval(waitForCsrfToken, 100);
        },

        register: function () {
            this.appView.showView(new GameViews.RegisterForm({ model: new GameModels.UserRegistrationModel() }));
        },

        userHome: function () {
            gameApp.games.fetch();
            this.appView.showView(new GameViews.UserHome({
                collection: gameApp.games
            }));
        },

        gameHome: function (gameId) {
            var game = gameApp.games.get(gameId);
            if (!game) {
                game = new GameModels.GameModel({"id": gameId});
                game.fetch();
                gameApp.games.add(game);
            }
            this.appView.showView(new GameViews.GameHome({
                model: game
            }));
        }
    });

    return GameRouters;
});