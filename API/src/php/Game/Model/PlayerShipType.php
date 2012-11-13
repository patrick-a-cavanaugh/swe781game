<?php

namespace Game\Model;

class PlayerShipType {

    /** The default ship_type.id. Always 0 because we don't have customizable ship types at the moment. */
    const DEFAULT_ID = 1;
    /** Default max cargo of the default ship type. We just throw this in here because ship types aren't customizable */
    const DEFAULT_MAX_CARGO = 100;
}
