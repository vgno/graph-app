<?php

namespace Graph\Controllers;

use Silex\Application;

use Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

use Everyman\Neo4j\Cypher\Query;

class RecommendationController {
    public function thoseWhoReadalsoReadAction(Request $req, Application $app, $articleId) {

        if (!isset($articleId) || !is_numeric($articleId)) {
            return new Response('missing-or-invalid-article-id', 400);
        }

        $articleId = (int) $articleId;
        $limit     = 10;

        if (isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] < 100) {
            $limit = (int) $_GET['limit'];
        }

        $recommended = new Query($app['neo4j'], sprintf('
            MATCH (article)<-[:read]-(user)-[:read]->(articleA)
            WHERE article.articleId = %d
            RETURN
                articleA.articleId as articleId,
                articleA.title as title,
                count(articleA) as reads
            ORDER BY reads DESC
            LIMIT %d', $articleId, $limit));

        $result = [];

        foreach ($recommended->getResultSet() as $row) {
            $result[] = [
                'id'    => $row['articleId'],
                'title' => $row['title']
            ];
        }

        return $app->json($result);
    }
}