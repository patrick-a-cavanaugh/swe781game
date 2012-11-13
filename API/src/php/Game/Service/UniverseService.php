<?php

namespace Game\Service;

/**
 * This service is concerned with setting up the universe for a new game. For instance, it creates the planets and
 * the hyperlinks between them.
 */
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use PDO;
use Game\Util\SecurityUtils;
use Exception;
use Monolog\Logger;
use Game\Util\User;

class UniverseService
{

    private static $planetsBasis = [
        ['name' => 'Xyon', 'hyperlinks' => ['Johansen']],
        ['name' => 'Johansen', 'hyperlinks' => ['Xyon', 'Polaris', 'Xeron'], 'is_start_location' => true],
        ['name' => 'Polaris', 'hyperlinks' => ['Johansen']],
        ['name' => 'Xeron', 'hyperlinks' => ['Johansen', 'Bismarck']],
        ['name' => 'Bismarck', 'hyperlinks' => ['Xeron', 'Imrio', 'Mercator']],
        ['name' => 'Imrio', 'hyperlinks' => ['Bismarck']],
        ['name' => 'Mercator', 'hyperlinks' => ['Bismarck']],
    ];

    private static $cargoPriceBasis = [
        // LOW
        100,
        // MEDIUM
        500,
        // HIGH
        900
    ];

    /** @var \Doctrine\DBAL\Connection */
    private $db;

    /** @var \Monolog\Logger */
    private $logger;

    function __construct(Connection $db, $logger, $user)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * @param int $gameId the id of the game to create a universe for. Should be a game that is not set up yet.
     * @throws \Exception if anything bad happens
     */
    public function createUniverseForGame($gameId)
    {
        $this->db->beginTransaction();
        try {
            $this->createPlanets($gameId);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function createPlanets($gameId)
    {
        // Every planet has every cargo type
        $cargoTypes = $this->db->fetchAll("SELECT id FROM cargo_type WHERE 1");

        // First create all the planets
        $planets = [];
        foreach (self::$planetsBasis as $planetBasis) {
            $this->db->insert('planet', ['game_id' => $gameId,
                'name' => $planetBasis['name'],
                'is_start_location' => isset($planetBasis['is_start_location']) ? $planetBasis['is_start_location'] : 0
            ], [PDO::PARAM_INT, PDO::PARAM_STR, PDO::PARAM_BOOL]);
            $planets[] = $this->db->fetchAssoc("SELECT id, game_id, name, is_start_location FROM planet WHERE id = ?",
                [$this->db->lastInsertId()]);
        }

        // Then add the links between them

        $findPlanet = function ($planetName) use ($planets) {
            $thisPlanet = null;
            foreach ($planets as $planet) {
                if ($planetName === $planet['name']) {
                    $thisPlanet = $planet;
                    break;
                }
            }
            if (is_null($thisPlanet)) {
                trigger_error('thisPlanet was null after looking for ' . $planetName, E_USER_ERROR);
            }
            return $thisPlanet;
        };

        foreach (self::$planetsBasis as $planetBasis) {
            $thisPlanet = $findPlanet($planetBasis['name']);

            foreach ($planetBasis['hyperlinks'] as $hyperlink) {
                $destPlanet = $findPlanet($hyperlink);
                $this->logger->addDebug('Creating hyperlink from planet '
                    . $thisPlanet['name'] . ' to ' . $destPlanet['name']);
                $this->db->insert('planet_link',
                    ['planet_id' => $thisPlanet['id'], 'connected_planet_id' => $destPlanet['id']],
                    [PDO::PARAM_INT, PDO::PARAM_INT]);
            }
        }

        // Then add the cargo to each planet

        foreach ($planets as $planet) {
            foreach ($cargoTypes as $cargoType) {
                // Use secure random to generate the cargo price so a clever user can't guess all the prices somehow.
                $sellIndex = SecurityUtils::secureRandom(0, 2);
                $sellPrice = self::$cargoPriceBasis[$sellIndex];
                // The buy price must be at least as much as the sell price.
                $buyPrice = self::$cargoPriceBasis[SecurityUtils::secureRandom($sellIndex, 2)];

                $this->db->insert('planet_cargo', [
                    'planet_id' => $planet['id'],
                    'cargo_type_id' => $cargoType['id'],
                    'buy_price' => $buyPrice,
                    'sell_price' => $sellPrice
                ]);
            }
        }
    }


    /**
     * @param $planetId
     * @return null|array
     */
    public function fetchPlanetById($planetId)
    {
        $planets = $this->fetchPlanetsWhere("p.id = :planetId", ['planetId' => intval($planetId)]);
        return empty($planets) ? null : $planets[0];
    }

    /**
     * @return array
     */
    public function fetchAllPlanets()
    {
        return $this->fetchPlanetsWhere("1 = 1");
    }

    private function fetchPlanetsWhere($whereCond, $whereParams = [])
    {
        $selectSql = self::getSqlWhere($whereCond);

        $planets = $this->db->fetchAll($selectSql, $whereParams);
        // Gets an associative array of all the links for all the planets selected by the fetchAll:
        $links = $this->getLinksForPlanets(array_map(function ($a) {
            return $a['id'];
        }, $planets));
        foreach ($links as $planetId => $linksForPlanet) {
            foreach ($planets as &$planet) {
                if (intval($planet['id']) == intval($planetId)) {
                    $planet['links'] = $linksForPlanet;
                }
            }
        }
        foreach($planets as &$planet) {
            $planet['id'] = intval($planet['id']);
            $planet['game_id'] = intval($planet['game_id']);
        }

        return $planets;
    }

    /**
     * @param $planetIds
     * @return array
     */
    private function getLinksForPlanets($planetIds)
    {
        $sql = <<<SQL
SELECT pl.connected_planet_id, plp.name AS connected_planet_name, pl.planet_id
FROM planet_link pl
INNER JOIN planet plp ON plp.id = pl.connected_planet_id
WHERE pl.planet_id IN (?)
SQL;
        $linksByPlanetId = [];

        /** @var $stmt \Doctrine\DBAL\Driver\Statement */
        $stmt = $this->db->executeQuery($sql,
            [$planetIds],
            [Connection::PARAM_INT_ARRAY]
        );

        $linkRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($linkRows as $link) {
            if (!isset($linksByPlanetId[$link['planet_id']])) {
                $linksByPlanetId[$link['planet_id']] = [];
            }
            $link['connected_planet_id'] = intval($link['connected_planet_id']);
            $link['planet_id'] = intval($link['planet_id']);
            $linksByPlanetId[$link['planet_id']][] = $link;
        }

        return $linksByPlanetId;
    }

    private static function getSqlWhere($whereCond = "1 = 1")
    {
        $sqlBase = <<<SQL
SELECT p.id, p.game_id, p.name
FROM planet p
WHERE 999 = 999
ORDER BY p.id ASC
SQL;
        return str_replace('999 = 999', $whereCond, $sqlBase);
    }
}
