<?php
namespace Evie\Rest\System\Record;

use Evie\Rest\System\Column\Reflection\ReflectedTable;

class OrderingInfo  {

    public function getColumnOrdering(ReflectedTable $table = null, array $params = []) {
        $fields = array();
        if (isset($params['order'])) {
            foreach ($params['order'] as $order) {
                $parts = explode(',', $order, 3);
                $columnName = $parts[0];
                if (!$table->exists($columnName)) {
                    continue;
                }
                $ascending = 'ASC';
                if (count($parts) > 1) {
                    if (substr(strtoupper($parts[1]), 0, 4) == "DESC") {
                        $ascending = 'DESC';
                    }
                }
                $fields[] = [$columnName, $ascending];
            }
        }
        if (count($fields) == 0) {
            $fields[] = [$table->getPk()->getName(), 'ASC'];
        }
        return $fields;
    }

}