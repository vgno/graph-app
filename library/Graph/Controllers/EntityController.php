<?php

namespace Graph\Controllers;

use Silex\Application;

use Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

use Everyman\Neo4j\Cypher\Query;

class EntityController {
    public function detectEntitiesAction(Request $req, Application $app) {
        $cacheKey = 'graph::entityExtraction::entityData';

        $body = $req->getContent();

        if (!strlen($body)) {
            return $app->json([], 200);
        }

        $entityData = $app['memcache']->get($cacheKey);

        if (!$entityData) {
            // Get entities
            $entityResult = new Query(
                $app['neo4j'],
                'MATCH (topic:topic)
                WHERE
                    NOT ("standard" IN labels(topic) OR "generell" IN labels(topic))
                RETURN
                    labels(topic) as labels,
                    topic.name as name,
                    topic.topicId as topicId;'
            );

            $entities = [];

            $singleWordEntities = [];
            $multiWordEntities  = [];

            foreach ($entityResult->getResultSet() as $row) {
                $key = trim(strtolower($row['name']));

                // We don't need them empty strings..
                if (!$key) {
                    continue;
                }

                $singleWordEntity = stripos($key, ' ') === false;

                // Skip if we've got this already
                if ($singleWordEntity && in_array($key, $singleWordEntities)) {
                    continue;
                } else if (!$singleWordEntity && in_array($key, $multiWordEntities)) {
                    continue;
                }

                $type = '';

                foreach ($row['labels'] as $label) {
                    if ($label == 'topic') {
                        continue;
                    }

                    $type = $label;
                    break;
                }

                $entities[$key] = [
                    'type' => $type,
                    'id'   => (int) $row['topicId'],
                    'name' => $row['name']
                ];

                if ($singleWordEntity) {
                    $singleWordEntities[] = $key;
                } else {
                    $multiWordEntities[] = $key;
                }
            }

            $app['memcache']->set($cacheKey, [
                'multi'  => $multiWordEntities,
                'single' => $singleWordEntities,
                'full'   => $entities
            ], 0, 300);
        } else {
            $multiWordEntities  = $entityData['multi'];
            $singleWordEntities = $entityData['single'];
            $entities           = $entityData['full'];
        }

        // Simple body text wash
        $textWords = str_replace("\n", '', $body);
        $textWords = strtolower($textWords);
        $textWords = explode(' ', $textWords);

        $textWords = array_unique(array_map(function($string) {
            return trim($string, ' .,!:;-–');
        }, $textWords));

        // Intersect words in text with the single word entities
        $entitiesInText = array_intersect($textWords, $singleWordEntities);

        // Search the text for multi word entities
        foreach ($multiWordEntities as $entity) {
            if (in_array($entity, $textWords)) {
                $entitiesInText[] = $entity;
            }
        }

        // Get the full entities for the ones that was found in text
        $entitiesInText = array_map(function($entity) use ($entities) {
            return $entities[$entity];
        }, $entitiesInText);

        // Reset index values
        $entitiesInText = array_values($entitiesInText);

        return $app->json($entitiesInText, 200);
    }
}