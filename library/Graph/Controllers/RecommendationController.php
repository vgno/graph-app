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
        $topics    = false;

        if (isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] < 100) {
            $limit = (int) $_GET['limit'];
        }

        if (isset($_GET['topics']) && is_array($_GET['topics'])) {
            $topics = array_filter(array_map(function($val) {
                if (!is_numeric($val)) {
                    return false;
                }

                return intval($val);
            }, $_GET['topics']));
        }

        // Filtering on topics
        if (count($topics)) {
            $recommended = new Query(
                $app['neo4j'],
                'MATCH (article:article)<-[:read]-(user:ip)-[:read]->(recommended:article)<-[:listed]-(topic:topic {topicId:1909})
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
                    'limit'     => $limit,
                    'topics'    => sprintf('[%s]', implode(',', $topics))
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