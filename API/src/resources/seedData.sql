-- some configuration data to bootstrap the game (e.g. ship types, cargo types)
INSERT INTO cargo_type (name)
    VALUES ('Iron'), ('Platinum'), ('Unobtanium'), ('Rock Salt');

INSERT INTO ship_type (id, cargo_space)
    VALUES (1, 100);