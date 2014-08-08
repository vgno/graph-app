<?php

namespace Graph;

use KBrabrand\Silex\Provider\Neo4jServiceProvider;

class Application extends \Silex\Application {
    public function __construct(array $config = array()) {
        parent::__construct();

        $this['debug']  = $config['silex']['debug'];
        $this['config'] = $config;
    }

    public function bootstrap() {
        $this->register(new Neo4jServiceProvider(), array(
            'neo4j.transport' => 'vg-neo4j-01',
            'neo4j.port'      => 7474,
        ));

        $this->get('/recommended/{articleId}',  'Graph\Controllers\RecommendationController::thoseWhoReadalsoReadAction');

        return $this;
    }
}