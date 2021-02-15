<?php

use Nette\Utils\Json;
use Phinx\Migration\AbstractMigration;

class AddKeyToScenarioConditionElementNodeParam extends AbstractMigration
{
    public function up()
    {
        /** @var PDO $pdo */
        $pdo = $this->getAdapter()->getConnection();
        $rows = $this->fetchAll('SELECT * FROM scenarios_elements WHERE `type` = "condition"');

        foreach ($rows as $row) {
            $options = Json::decode($row['options']);
            $updatedOptions = $options;

            $updatedNodes = [];
            foreach ($options->conditions->nodes as $node) {
                if (isset($node->params)) {
                    // migration already done for this row
                    continue 2;
                }

                $updatedNode = (object) [
                    'id' => $node->id, // ID is an internal information of ScenarioBuilder
                    'key' => $node->key,
                    'params' => [
                        [
                            // previously used convention, key of param was the same as key of criteria
                            'key' => $node->key,
                            'values' => $node->values,
                        ]
                    ]
                ];
                $updatedNodes[] = $updatedNode;
            }
            $updatedOptions->conditions->nodes = $updatedNodes;
            $updatedOptions = Json::encode($updatedOptions);

            // Using bind parameters to correctly escape JSON value
            $stmt = $pdo->prepare('UPDATE scenarios_elements SET options = :options WHERE id = :id');
            $stmt->bindParam(':options', $updatedOptions);
            $stmt->bindParam(':id', $row['id']);
            if (!$stmt->execute()) {
                throw new \Exception("Unable to execute 'scenario_elements' for ID {$row['id']}");
            }
        }

        $pdo = null;
    }

    public function down()
    {
        // First check if migration down is possible
        $rows = $this->fetchAll('SELECT * FROM scenarios_elements WHERE `type` = "condition"');
        $unableToDownMigrate = false;

        foreach ($rows as $row) {
            $options = Json::decode($row['options']);

            foreach ($options->conditions->nodes as $node) {
                if (count($node->params) !== 1) {
                    $unableToDownMigrate = true;
                    break 2;
                }
                if ($node->params[0]->key !== $node->key) {
                    $unableToDownMigrate = true;
                    break 2;
                }
            }
        }

        if ($unableToDownMigrate) {
            $this->output->writeln('Unable to down migrate because of incompatible data (some condition criteria have more than one parameter registered).');
            return;
        }

        // The actual migration
        /** @var PDO $pdo */
        $pdo = $this->getAdapter()->getConnection();
        foreach ($rows as $row) {
            $options = Json::decode($row['options']);

            $updatedOptions = $options;

            $updatedNodes = [];
            foreach ($options->conditions->nodes as $node) {
                $updatedNode = (object) [
                    'id' => $node->id,
                    'key' => $node->key,
                    'values' => $node->params[0]->values,
                ];
                $updatedNodes[] = $updatedNode;
            }
            $updatedOptions->conditions->nodes = $updatedNodes;
            $updatedOptions = Json::encode($updatedOptions);

            // Using bind parameters to correctly escape JSON value
            $stmt = $pdo->prepare('UPDATE scenarios_elements SET options = :options WHERE id = :id');
            $stmt->bindParam(':options', $updatedOptions);
            $stmt->bindParam(':id', $row['id']);
            if (!$stmt->execute()) {
                throw new \Exception("Unable to execute 'scenario_elements' for ID {$row['id']}");
            }
        }

        $pdo = null;
    }
}
