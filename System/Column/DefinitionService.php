<?php
namespace Evie\Rest\System\Column;

use Evie\Rest\System\Column\Reflection\ReflectedColumn;
use Evie\Rest\System\Column\Reflection\ReflectedTable;
use Evie\Rest\System\Database\GenericDB;

class DefinitionService {

    private $_db            = null;
    private $_reflection    = null;

    public function __construct(GenericDB $db = null, ReflectionService $reflection = null)   {
        $this->_db = $db;
        $this->_reflection = $reflection;
    }

    public function updateTable($tableName = null, $changes = null) {
        $table = $this->_reflection->getTable($tableName);
        $newTable = ReflectedTable::fromJson((object) array_merge((array) $table->jsonSerialize(), (array) $changes));
        if ($table->getName() != $newTable->getName()) {
            if (!$this->_db->definition()->renameTable($table->getName(), $newTable->getName())) {
                return false;
            }
        }
        return true;
    }

    public function updateColumn($tableName = null, $columnName = null, $changes = null)  {
        $table = $this->_reflection->getTable($tableName);
        $column = $table->get($columnName);

        $newColumn = ReflectedColumn::fromJson((object) array_merge((array) $column->jsonSerialize(), (array) $changes));
        if ($newColumn->getPk() != $column->getPk() && $table->hasPk()) {
            $oldColumn = $table->getPk();
            if ($oldColumn->getName() != $columnName) {
                $oldColumn->setPk(false);
                if (!$this->_db->definition()->removeColumnPrimaryKey($table->getName(), $oldColumn->getName(), $oldColumn)) {
                    return false;
                }
            }
        }

        $newColumn = ReflectedColumn::fromJson((object) array_merge((array) $column->jsonSerialize(), ['pk' => false, 'fk' => false]));
        if ($newColumn->getPk() != $column->getPk() && !$newColumn->getPk()) {
            if (!$this->_db->definition()->removeColumnPrimaryKey($table->getName(), $column->getName(), $newColumn)) {
                return false;
            }
        }
        if ($newColumn->getFk() != $column->getFk() && !$newColumn->getFk()) {
            if (!$this->_db->definition()->removeColumnForeignKey($table->getName(), $column->getName(), $newColumn)) {
                return false;
            }
        }

        $newColumn = ReflectedColumn::fromJson((object) array_merge((array) $column->jsonSerialize(), (array) $changes));
        $newColumn->setPk(false);
        $newColumn->setFk('');
        if ($newColumn->getName() != $column->getName()) {
            if (!$this->_db->definition()->renameColumn($table->getName(), $column->getName(), $newColumn)) {
                return false;
            }
        }
        if ($newColumn->getType() != $column->getType() ||
            $newColumn->getLength() != $column->getLength() ||
            $newColumn->getPrecision() != $column->getPrecision() ||
            $newColumn->getScale() != $column->getScale()
        ) {
            if (!$this->_db->definition()->retypeColumn($table->getName(), $newColumn->getName(), $newColumn)) {
                return false;
            }
        }
        if ($newColumn->getNullable() != $column->getNullable()) {
            if (!$this->_db->definition()->setColumnNullable($table->getName(), $newColumn->getName(), $newColumn)) {
                return false;
            }
        }

        $newColumn = ReflectedColumn::fromJson((object) array_merge((array) $column->jsonSerialize(), (array) $changes));
        if ($newColumn->getFk()) {
            if (!$this->_db->definition()->addColumnForeignKey($table->getName(), $newColumn->getName(), $newColumn)) {
                return false;
            }
        }
        if ($newColumn->getPk()) {
            if (!$this->_db->definition()->addColumnPrimaryKey($table->getName(), $newColumn->getName(), $newColumn)) {
                return false;
            }
        }
        return true;
    }

    public function addTable($definition = null)    {
        $newTable = ReflectedTable::fromJson($definition);
        if (!$this->_db->definition()->addTable($newTable)) {
            return false;
        }
        return true;
    }

    public function addColumn($tableName = null, $definition = null)    {
        $newColumn = ReflectedColumn::fromJson($definition);
        if (!$this->_db->definition()->addColumn($tableName, $newColumn)) {
            return false;
        }
        if ($newColumn->getFk()) {
            if (!$this->_db->definition()->addColumnForeignKey($tableName, $newColumn->getName(), $newColumn)) {
                return false;
            }
        }
        if ($newColumn->getPk()) {
            if (!$this->_db->definition()->addColumnPrimaryKey($tableName, $newColumn->getName(), $newColumn)) {
                return false;
            }
        }
        return true;
    }

    public function removeTable($tableName = null)  {
        if (!$this->_db->definition()->removeTable($tableName)) {
            return false;
        }
        return true;
    }

    public function removeColumn($tableName = null, $columnName = null) {
        $table = $this->_reflection->getTable($tableName);
        $newColumn = $table->get($columnName);
        if ($newColumn->getPk()) {
            $newColumn->setPk(false);
            if (!$this->_db->definition()->removeColumnPrimaryKey($table->getName(), $newColumn->getName(), $newColumn)) {
                return false;
            }
        }
        if ($newColumn->getFk()) {
            $newColumn->setFk("");
            if (!$this->_db->definition()->removeColumnForeignKey($tableName, $columnName, $newColumn)) {
                return false;
            }
        }
        if (!$this->_db->definition()->removeColumn($tableName, $columnName)) {
            return false;
        }
        return true;
    }


}