define(['backbone', 'jquery', 'custom/gameModels', 'humps'], function (Backbone, $, GameModels, humps) {
    var GameViews = {};

    Backbone.View.prototype.close = function () {
        this.remove();
        this.unbind();
        if (this.onClose) {
            this.onClose();
        }
    };

    GameViews.AppView = function () {
        // From http://lostechies.com/derickbailey/2011/09/15/zombies-run-managing-page-transitions-in-backbone-apps/
        this.showView = function (view) {
            if (this.currentView) {
                this.currentView.close();
            }

            this.currentView = view;
            this.currentView.render();

            $("#mainFrame").html(this.currentView.el);
        }
    };

    GameViews.LoginForm = Backbone.View.extend({

        template: _.template($("#loginFormTmpl").html()),

        initialize: function () {
        },

        render: function () {
            this.$el.html(this.template({
                loginFormHtml: this.options.loginFormHtml
            }));
        }
    });

    GameViews.StatisticsHome = Backbone.View.extend({
        template: _.template($("#statisticsTmpl").html()),

        initialize: function () {
            this.model.on('change', this.render, this);
        },

        render: function () {
            var json = this.model.toJSON();
            this.$el.html(this.template({
                playerRankings: json.playerRankings,
                gameMoves: json.gameMoves
            }));
        }
    });

    GameViews.GameListItem = Backbone.View.extend({

        events: {
            "click .joinGameBtn": "joinGame",
            "click .startGameBtn": "startGame"
        },

        tagName: "tr",

        template: _.template($("#userHomeGameListItemTmpl").html()),

        initialize: function () {
            this.model.bind('change', this.render, this);
            this.model.bind('destroy', this.remove, this);
        },

        render: function () {
            console.log("Rendering GameListItem");
            this.$el.html(this.template({"game": this.model}));
            return this;
        },

        joinGame: function () {
            this.model.save({
                "joined": true
            });
        },

        startGame: function () {
            this.model.save({
                "status": "IN_PROGRESS"
            });
        }

    });

    GameViews.UserHome = Backbone.View.extend({

        events: {
            "click #createGameBtn": "createGame"
        },

        template: _.template($("#userHomeTmpl").html()),

        initialize: function () {
            console.log("in UserHome initialize");
            this.collection.bind('reset', this.onGameListReset, this);
            this.collection.bind('sync', this.onGameListSync, this);

            this._gameViews = [];
        },

        render: function () {
            this.$el.html(this.template({
                collection: this.collection
            }));

            if (this._gameViews.length > 0) {
                var tbody = this.$el.find('table tbody');
                tbody.empty();
                _(this._gameViews).each(function (gv) {
                    tbody.append(gv.render().$el);
                })
            }
        },

        onGameListReset: function (resetCollection) {
            var self = this;
            this.collection.each(function (game) {
                self._gameViews.push(new GameViews.GameListItem({
                    model: game
                }));
            });
            this.render();
        },

        onGameListSync: function (changedModel) {
            console.log("onGameListSync", changedModel);
            this._gameViews.unshift(new GameViews.GameListItem({
                model: changedModel
            }));
            this.render();
        },

        createGame: function (clickEvent) {
            var modal = $(clickEvent.target).closest('.modal');
            var gameNameInput = modal.find('input[type="text"][name="name"]');
            var gameName = gameNameInput.val();

            modal.modal('hide'); // hide it here too to prevent a bug with the background after validation failure

            this.collection.create({
                "name": gameName
            }, {
                wait: true,
                error: function (model, xhr, options) {
                    try {
                        var errors = $.parseJSON(xhr.responseText);
                        var controlGroup = gameNameInput.closest('.control-group');
                        controlGroup.find('.help-inline').remove();
                        controlGroup.addClass('error');
                        controlGroup.find('input')
                            .after($("<span></span>").text(errors.name[0]).addClass('help-inline'));
                        modal.modal('show');
                    } catch (e) {
                        console.error(e);
                    }
                }
            });
        }
    });

    GameViews.GameHome = Backbone.View.extend({
        template: _.template($("#gameHomeTmpl").html()), // TODO add tmpl

        events: {
            "click .btnLiftoff": 'liftOffShip',
            "click .btnLand": 'landShip',
            "click .btnJump": 'moveShip',
            "click #buyButton": 'commerceBuy',
            "click #sellButton": 'commerceSell'
        },

        initialize: function () {
            this.commerceErrors = [];
            this.model.on('change', this.fetchNeededData, this);
            if (this.model.get('joined')) {
                // only way we know joined is true is after data loaded from server.
                this.fetchNeededData();
            }

            if (!gameApp.planets) {
                gameApp.planets = new GameModels.PlanetList();
                gameApp.planets.fetch();
            }
            gameApp.planets.bind('reset', this.render, this);
            gameApp.planets.bind('change', this.render, this);
            this.model.bind('reset', this.render, this);
            this.model.bind('change', this.render, this);
        },

        fetchNeededData: function () {
            var currentUserPlayerId = this.model.get('currentUserPlayerId');
            console.log("GameViews.GameHome#fetchNeededData", currentUserPlayerId);
            if (currentUserPlayerId && !this.player) {
                var existingPlayer = gameApp.players.get(currentUserPlayerId);
                if (existingPlayer) {
                    this.player = existingPlayer;
                } else {
                    this.player = new GameModels.GamePlayer({"id": this.model.get('currentUserPlayerId')});
                    this.player.on('change', this.render, this);
                    this.player.on('reset', this.render, this);
                    this.player.fetch();
                    gameApp.players.add(this.player, {silent: true});
                }
            }
        },

        render: function () {
            console.log("Rendering GameHome, this.player: ", this.player != null);
            var rendered = false;
            if (this.player) {
                var currentPlanets = gameApp.planets.where({"id": this.player.get('locationId') });
                var currentPlanet = currentPlanets && currentPlanets.length ? currentPlanets[0] : null;

                console.log("planets length", gameApp.planets.length,
                            "location id", this.player.get('locationId'));
                console.log("should load cargo: ", currentPlanet);
                if (currentPlanet) {
                    console.log("should load cargo: cargoLoaded = ", currentPlanet.cargoLoaded);
                    console.log("should load cargo: cargoLoading = ", currentPlanet.cargoLoading);
                }
                if (currentPlanet && !currentPlanet.cargoLoaded && !currentPlanet.cargoLoading) {
                    currentPlanet.loadCargo();
                }

                console.log("currentPlanet", currentPlanet != false, "currentPlanet.cargoLoaded",
                        currentPlanet && currentPlanet.cargoLoaded != false);

                if (currentPlanet && currentPlanet.cargoLoaded) {
                    console.log("Rendering GameHome template");
                    this.$el.html(this.template({
                        game: this.model,
                        gameMapUrl: gameApp.gameApiBaseUrl + '/games/0/map',
                        player: this.player,
                        planets: gameApp.planets,
                        currentPlanet: currentPlanet,
                        commerceErrors: this.commerceErrors,
                        commerceFormParams: (this.commerceFormParams || {})
                    }));
                    rendered = true;
                }
            }

            if (!rendered) {
                this.$el.html("Loading data, please wait…");
            }
            return this;
        },

        _makeMove: function (type, destinationId) {
            var newMove = new GameModels.PlayerMove({
                playerId: this.player.get('id'),
                gameTurnNo: this.player.get('lastCompletedTurn') + 1,
                moveNo: this.player.get('lastMoveNo') + 1,
                type: type,
                playerMoveDestinationId: destinationId ? destinationId : this.player.get('locationId')
            });
            var self = this;
            console.log("Saving move");
            newMove.save({success: function (model, response, options) {
                console.log("Attempting to Fetch1");
                self.model.fetch();
            }}, {success: function (model, response, options) {
                console.log("Attempting to Fetch2");
                self.model.fetch();
            }});
            this.player.get('moves').add(newMove);
        },

        _makeCommerceTransaction: function (isBuy) {
            var self = this;
            var cargoTypeId = this.$el.find('#cargoTypeIdSelect').val();
            var cargoSize = this.$el.find('#sizeInput').val();

            // reset the errors and such
            this.commerceErrors = [];
            this.commerceFormParams = {};

            var params = {
                url: gameApp.gameApiBaseUrl + "/playerCargoTransactions",
                type: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                data: JSON.stringify({
                    type: isBuy ? "BUY" : "SELL",
                    cargo_type_id: parseInt(cargoTypeId),
                    size: parseInt(cargoSize),
                    player_id: this.player.get('id')
                })
            };

            var promise = $.ajax(params);
            promise.done(function (data) {
                self.player.set(humps.camelizeKeys(data.player));
            });
            promise.fail(function (xhr, errorString, exception) {
                if (xhr.status === 400) {
                    var errorsByKey = $.parseJSON(xhr.responseText);
                    $.each(errorsByKey, function (index, errorMessages) {
                        $.each(errorMessages, function (i, errorMessage) {
                            self.commerceErrors.push(index + ": " + errorMessage);
                        });
                    });
                    self.commerceFormParams = {
                        "cargoTypeIdSelect": cargoTypeId,
                        "sizeInput": cargoSize
                    };
                    self.render();
                }
            });
        },

        liftOffShip: function () {
            this._makeMove('LIFTOFF');
        },

        landShip: function () {
            this._makeMove('LAND');
        },

        moveShip: function () {
            this._makeMove('JUMP', parseInt(this.$el.find('#jumpSelect').val()));
        },

        commerceBuy: function () {
            this._makeCommerceTransaction(true);
        },

        commerceSell: function () {
            this._makeCommerceTransaction(false);
        }
    });

    GameViews.RegisterForm = Backbone.View.extend({
        template: _.template($("#registerFormTmpl").html()),

        events: {
            "submit form": "eventSubmit"
        },

        initialize: function () {

        },

        render: function () {
            console.log("rendering RegisterForm");
            var formValues = {};
            if (this.renderedOnce) { // If the form is already on the page grab the latest values from it
                formValues = this.$el.find("form").serializeObject();
                this.model.set(formValues);
            } else {
                this.renderedOnce = true;
            }
            this.$el.html(this.template({
                form: this.model
            }));
            return this;
        },

        eventSubmit: function (submitEvent) {
            submitEvent.preventDefault();
            var self = this,
                form = this.$el.find("form"),
                formValues = form.serialize();

            var request = $.ajax({
                url: gameApp.gameApiBaseUrl + "/user/create",
                type: 'POST',
                data: formValues
            });
            request.done(function (data, textStatus, xhr) {
                //console.log(data, textStatus, xhr);
                self.model.set({"errors": {}});
                alert("You have registered successfully. Please check your email" +
                    " for a confirmation message to complete your registration.");
                self.render();
            });
            request.fail(function (xhr, textStatus) {
                //console.error(xhr, textStatus);
                var responseJson = $.parseJSON(xhr.responseText);
                self.model.set({"errors": responseJson});
                self.render();
            });
        }
    });

    return GameViews;
});