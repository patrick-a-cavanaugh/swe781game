define(['backbone', 'jquery', 'custom/gameApp', 'humps'], function (Backbone, $, gameApp, humps) {

    var GameModels = {};

    GameModels.UserRegistrationModel = Backbone.Model.extend({
        defaults: {
            emailAddress: "",
            userName: "",
            password: "",
            passwordConfirmation: "",

            errors: {}
        },

        error: function (key) {
            console.log("looking for errors for " + key, this.errors);
            var errors = this.get('errors');
            if (errors[key] && errors[key].length > 0) {
                var errorSpan = $("<span></span>").addClass("help-inline");
                _.each(errors[key], function (element, index) {
                    errorSpan.append($("<span></span>").text(errors[key][index]));
                    if (index < (errors[key].length - 2)) {
                        errorSpan.after($("<br />"));
                    }
                }, this);
                return $("<div></div>").append(errorSpan).html();
            }
            return "";
        },
        errorClass: function (key) {
            if (typeof key === 'object') {
                return _.reduce(key, function (memo, value) {
                    return memo === '' ? this.errorClass(value) : memo;
                }, '', this);
            }
            var errors = this.get('errors');
            return errors && errors[key] && errors[key].length > 0 ? "error" : "";
        }
    });

    GameModels.GameModel = Backbone.Model.extend({

        urlRoot: gameApp.gameApiBaseUrl + "/games",

        defaults: {
            id: null,
            dateCreated: null,
            dateStarted: null,
            status: null,
            name: null,
            players: 0,
            turn: 0,
            createdById: null,
            // If the current player is in the game already
            joined: 0,
            currentUserPlayerId: null,
            winnerId: null,
            winnerName: null
        },

        parse: function (response) {
            if (response.player) {
                var newPlayer = humps.camelizeKeys(response.player);
                var player = gameApp.players.get(response.player.id);
                if (player) {
                    player.set(newPlayer);
                } else {
                    gameApp.players.add(newPlayer);
                }
            }
            return humps.camelizeKeys(response.game ? response.game : response);
        },

        createdByMe: function () {
            return gameApp.user !== null && (this.get('createdById') == gameApp.user.id);
        }
    });

    GameModels.GameList = Backbone.Collection.extend({
        model: GameModels.GameModel,
        url: gameApp.gameApiBaseUrl + "/games",

        parse: function (response) {
            console.log("in GameList.parse", response);
            return response.gameList;
        }
    });

    GameModels.PlayerMove = Backbone.Model.extend({
        defaults: {
            id: null,
            playerId: null,
            gameTurnNo: null,
            moveNo: null,
            type: null,
            playerMoveDestinationId: null
        },

        initialize: function () {
            this.setBaseUrlFromPlayerId(); //
            this.on("change:playerId", this.setBaseUrlFromPlayerId, this);
            this.on("change:id", this.onConfirmServerSave, this);
        },

        parse: function (response) {
            if (response.player) {
                var player = gameApp.players.get(response.player.id);
                console.log("updating player ", response.player.id);
                player.set(humps.camelizeKeys(response.player));
            }
            if (response.currentLocation ) {
                var currentLocation = gameApp.planets.get(response.currentLocation.id);
                console.log("updating planet ", response.currentLocation.id);
                currentLocation.set(humps.camelizeKeys(response.currentLocation));
            }
            return humps.camelizeKeys(response.playerMove);
        },

        setBaseUrlFromPlayerId: function () {
            this.url = gameApp.gameApiBaseUrl + "/playerMoves/"
                + encodeURIComponent(this.get('playerId')) + "/moves";
        },

        onConfirmServerSave: function () {
            var player = gameApp.players.get(this.get('playerId'));
            if (player) {
                var moves = player.get('moves');
                if (moves) {
                    moves.add(this);
                    player.trigger('change');
                    player.trigger('change:moves');
                }
            }
        }
    });

    GameModels.PlayerMoveList = Backbone.Collection.extend({
        model: GameModels.PlayerMove,

        initialize: function (models, options) {
            if (options.playerId == null) {
                throw "You must set a playerId for the move list";
            }
            this.url = gameApp.gameApiBaseUrl + "/players/"
                + encodeURIComponent(options.playerId) + "/moves";
        },

        parse: function (response) {
            return response.playerMoveList;
        }
    });

    GameModels.GamePlayer = Backbone.Model.extend({

        urlRoot: gameApp.gameApiBaseUrl + "/players",

        defaults: {
            id: null,
            userId: null,
            gameId: null,
            dateEntered: null,
            status: null,
            locationId: null,
            locationStatus: null, // was last move type 'LIFTOFF', 'LAND', 'HYPERJUMP'
            fuel: null,
            maxCargoSpace: null,
            freeCargoSpace: null,
            cargo: [],

            moves: null // GameModels.PlayerMoveList
        },

        initialize: function () {
            this.on('change:moves', this._calculateValuesFromMoves, this);
            if (this.get('moves') != null) {
                this._calculateValuesFromMoves();
            }
        },

        parse: function (response) {
            var baseObject = humps.camelizeKeys(response.player ? response.player : response);
            baseObject.moves = new GameModels.PlayerMoveList(baseObject.moves, {"playerId": baseObject.id});
            return baseObject;
        },

        _calculateValuesFromMoves: function () {
            var locationStatus = 'LAND'; // default.
            var lastCompletedTurn = -1; // the default if no turns have been completed
            var lastMoveNo = -1;

            console.log("in _calculateValuesFromMoves");
            var highestMoveId = -1; // lower than 0, the lowest possible id.
            this.get('moves').each(function (gameMove) {
                if (gameMove.get('id') > highestMoveId) {
                    highestMoveId = gameMove.get('id');
                    lastMoveNo = gameMove.get('moveNo');
                    locationStatus = gameMove.get('type');
                    if (locationStatus === 'LAND') {
                        lastCompletedTurn = gameMove.get('gameTurnNo');
                    }
                }
            });
            this.set({
                'lastMoveNo': lastMoveNo,
                'locationStatus': locationStatus,
                'lastCompletedTurn': lastCompletedTurn
            });
        }
    });

    GameModels.GamePlayerList = Backbone.Collection.extend({
        model: GameModels.GamePlayer,

        // This URL doesn't exist at this time, for fetching the full list…
        url: gameApp.gameApiBaseUrl + "/players",

        parse: function (response) {
            return response.gamePlayerList;
        }
    });

    GameModels.Planet = Backbone.Model.extend({
        defaults: {
            id: null,
            gameId: null,
            name: null,
            cargo: [],
            links: []
        },

        initialize: function () {
            this.cargoLoaded = false;
            this.bind('change:cargo', this.onCargoLoaded, this);
        },

        parse: function (response) {
            return humps.camelizeKeys(response.planet ? response.planet : response);
        },

        loadCargo: function () {
            var self = this;
            var promise = $.ajax({
                url: gameApp.gameApiBaseUrl + "/planets/" + encodeURIComponent(this.get('id')) + '/cargo',
                type: "get",
                dataType: "json"
            });
            promise.done(function (data, textStatus, xhr) {
                console.log("in loadCargo promise.done");
                self.set('cargo', humps.camelizeKeys(data.cargoList));
            });

            this.cargoLoading = true;
        },

        onCargoLoaded: function () {
            this.cargoLoaded = true;
            console.log("Cargo loaded for planet #" + this.get('id'));
        }
    });

    GameModels.PlanetList = Backbone.Collection.extend({
        model: GameModels.Planet,

        url: gameApp.gameApiBaseUrl + "/planets",

        parse: function (response) {
            return response.planetList;
        }
    });

    GameModels.StatisticsAggregate = Backbone.Model.extend({

        defaults: {
            "playerRankings": null,
            "gameMoves": null
        },

        parse: function (response) {
            return humps.camelizeKeys(response);
        }

    });

    return GameModels;
});