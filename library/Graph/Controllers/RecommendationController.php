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
        $topics    = isset($_GET['topics']) ? $_GET['topics'] : [];

        if (isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] < 100) {
            $limit = (int) $_GET['limit'];
        }

        $topics = is_string($topics) ? explode(',', $topics) : $topics;
        $topics = array_filter(array_map(function($val) {
            if (!is_numeric($val)) {
                return false;
            }

            return intval($val);
        }, $topics));

        // Filtering on topics
        if (count($topics)) {
            $recommended = new Query(
                $app['neo4j'],
                'MATCH (article:article)<-[:read]-(user:ip)-[:read]->(recommended:article)<-[:listed]-(topic:topic)
                WHERE
                    article.articleId = {articleId}
                    AND topic.topicId IN [' . implode(',', $topics) . ']
                RETURN
                    recommended.articleId as articleId,
                    recommended.title as title,
                    count(*) as reads
                ORDER BY reads DESC
                LIMIT {limit}',
                [
                    'articleId' => $articleId,
                    'limit'     => $limit
                ]
            );
        } else {
            $recommended = new Query(
                $app['neo4j'],
                'MATCH (article:article)<-[:read]-(user:ip)-[:read]->(recommended:article)
                WHERE
                    article.articleId = {articleId}
                RETURN
                    recommended.articleId as articleId,
                    recommended.title as title,
                    count(*) as reads
                ORDER BY reads DESC
                LIMIT {limit}',
                [
                    'articleId' => $articleId,
                    'limit'     => $limit
                ]
            );
        }

        $result = [];

        foreach ($recommended->getResultSet() as $row) {
            $result[] = [
                'id'    => $row['articleId'],
                'title' => $row['title']
            ];
        }

        return $app->json($result, 200, [
            'Cache-Control' => 'max-age=300'
        ]);
    }
}