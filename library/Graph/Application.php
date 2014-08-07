<?php

namespace Graph;

class Application extends \Silex\Application {
    public function __construct(array $config = array()) {
        parent::__construct();

        $this['debug']  = $config['silex']['config'];
        $this['config'] = $config;
    }

    public function bootstrap() {
        $app->register(new Neo4jServiceProvider(), array(
            'neo4j.transport' => 'localhost',
            'neo4j.port'      => 7474,
        ));

        $this->get('/recommended/{articleId}',  Graph\Controllers\Recommendation::signUp);
    }
}