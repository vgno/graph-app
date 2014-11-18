<?php

namespace Graph;

use KBrabrand\Silex\Provider\Neo4jServiceProvider;

use SilexMemcache\MemcacheExtension;

use JDesrosiers\Silex\Provider\CorsServiceProvider;

use Symfony\Component\HttpFoundation\JsonResponse,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

class Application extends \Silex\Application {
    public function __construct(array $config = array()) {
        parent::__construct();

        $this['debug']  = $config['silex']['debug'];
        $this['config'] = $config;
    }

    public function bootstrap() {
        $this->register(new Neo4jServiceProvider(), [
            'neo4j.transport' => $this['config']['neo4j']['transport'],
            'neo4j.port'      => $this['config']['neo4j']['port'],
        ]);

        $this->register(new MemcacheExtension(), [
            'memcache.library' => 'memcached',
            'memcache.server'  => $this['config']['memcache']['hosts']
        ]);

        $this->register(new CorsServiceProvider(), [
            'cors.allowOrigin' => $this['config']['cors']['allowOrigin'],
        ])->after($this['cors']);

        $this->after(function(Request $req, Response $res) {
            $callback = $req->get('callback');

            if ($callback !== null && $req->getMethod() === 'GET') {
                if ($res instanceof JsonResponse) {
                    $res->setCallBack($callback);
                } else {
                    $res->setContent($callback . '(' . $res->getContent() . ');');
                }
            }
        });

        $this->get('/recommended/{articleId}', 'Graph\Controllers\RecommendationController::thoseWhoReadalsoReadAction');
        $this->post('/detectEntities',         'Graph\Controllers\EntityController::detectEntitiesAction');
        $this->get('/suggestEntities',         'Graph\Controllers\EntityController::suggestEntitiesAction');

        return $this;
    }
}
