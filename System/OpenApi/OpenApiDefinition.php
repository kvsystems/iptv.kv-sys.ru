<?php
namespace Evie\Rest\System\OpenApi;

class OpenApiDefinition extends DefaultOpenApiDefinition    {

    private function _set($path = null, $value = null)  {
        $parts = explode('/', trim($path, '/'));
        $current = &$this->root;
        while (count($parts) > 0) {
            $part = array_shift($parts);
            if (!isset($current[$part])) {
                $current[$part] = [];
            }
            $current = &$current[$part];
        }
        $current = $value;
    }

    private function _fillParametersWithPrimaryKey($method = null, TableDefinition $table = null)   {
        if ($table->getPk() != null) {
            $pathWithId = sprintf('/records/%s/{%s}', $table->getName(), $table->getPk()->getName());
            $this->_set("/paths/$pathWithId/$method/responses/200/description", "$method operation");
        }
    }

    public function setPaths(DatabaseDefinition $database, TableDefinition $table = null)  {
        foreach ($database->getTables() as $database) {
            $path = sprintf('/records/%s', $table->getName());
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                $this->_set("/paths/$path/$method/description", "$method operation");
            }
        }
    }

}