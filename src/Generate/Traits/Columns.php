<?php

namespace Brackets\AdminGenerator\Generate\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait Columns
{

	/**
	 * @param $tableName
	 * @return Collection
	 */
	protected function readColumnsFromTable($tableName): Collection
	{
		// Laravel 11: Používame Schema::getIndexes(), ale správne iterujeme
		$indexes = collect(Schema::getIndexes($tableName));

		return collect(Schema::getColumnListing($tableName))->map(function ($columnName) use ($tableName, $indexes) {
			// Skontrolujeme, či index obsahuje metódu getColumns() - v Laravel 11 je to pole
			$columnUniqueIndexes = $indexes->filter(function ($index) use ($columnName) {
				return isset($index['columns']) && in_array($columnName, $index['columns']) &&
					(isset($index['unique']) && $index['unique'] && !isset($index['primary']));
			});

			$columnUniqueDeleteAtCondition = $columnUniqueIndexes->filter(function ($index) {
				return isset($index['options']['where']) && $index['options']['where'] == '(deleted_at IS NULL)';
			});

			// Použitie Laravel-native getColumnType()
			$columnType = Schema::getColumnType($tableName, $columnName);

			// Nahradenie Doctrine getDoctrineColumn() za SQL dotaz
			$columnDetails = DB::selectOne("SHOW COLUMNS FROM `$tableName` WHERE Field = ?", [$columnName]);

			return [
				'name' => $columnName,
				'type' => $columnType,
				'required' => isset($columnDetails->Null) && $columnDetails->Null === 'NO', // Kontrola NOT NULL
				'unique' => $columnUniqueIndexes->isNotEmpty(),
				'unique_deleted_at_condition' => $columnUniqueDeleteAtCondition->isNotEmpty(),
			];
		});
	}

	protected function getVisibleColumns($tableName, $modelVariableName)
	{
		$columns = $this->readColumnsFromTable($tableName);
		$hasSoftDelete = ($columns->filter(function ($column) {
			return $column['name'] == "deleted_at";
		})->count() > 0);
		return $columns->filter(function ($column) {
			return !in_array($column['name'],  ["id", "created_at", "updated_at", "deleted_at", "remember_token", "last_login_at"]);
		})->map(function ($column) use ($tableName, $hasSoftDelete, $modelVariableName) {
			$serverStoreRules = collect([]);
			$serverUpdateRules = collect([]);
			$frontendRules = collect([]);
			if ($column['required']) {
				$serverStoreRules->push('\'required\'');
				$serverUpdateRules->push('\'sometimes\'');
				if ($column['type'] != 'boolean' && $column['name'] != 'password') {
					$frontendRules->push('required');
				}
			} else {
				$serverStoreRules->push('\'nullable\'');
				$serverUpdateRules->push('\'nullable\'');
			}

			if ($column['name'] == 'email') {
				$serverStoreRules->push('\'email\'');
				$serverUpdateRules->push('\'email\'');
				$frontendRules->push('email');
			}

			if ($column['name'] == 'password') {
				$serverStoreRules->push('\'confirmed\'');
				$serverUpdateRules->push('\'confirmed\'');
				$frontendRules->push('confirmed:password');

				$serverStoreRules->push('\'min:7\'');
				$serverUpdateRules->push('\'min:7\'');
				$frontendRules->push('min:7');

				$serverStoreRules->push('\'regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9]).*$/\'');
				$serverUpdateRules->push('\'regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9]).*$/\'');
				//TODO not working, need fixing
				//                $frontendRules->push(''regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[!$#%]).*$/g'');
			}

			if ($column['unique'] || $column['name'] == 'slug') {
				if ($column['type'] == 'json') {
					$storeRule = 'Rule::unique(\'' . $tableName . '\', \'' . $column['name'] . '->\'.$locale)';
					$updateRule = 'Rule::unique(\'' . $tableName . '\', \'' . $column['name'] . '->\'.$locale)->ignore($this->' . $modelVariableName . '->getKey(), $this->' . $modelVariableName . '->getKeyName())';
					if ($hasSoftDelete && $column['unique_deleted_at_condition']) {
						$storeRule .= '->whereNull(\'deleted_at\')';
						$updateRule .= '->whereNull(\'deleted_at\')';
					}
					$serverStoreRules->push($storeRule);
					$serverUpdateRules->push($updateRule);
				} else {
					$storeRule = 'Rule::unique(\'' . $tableName . '\', \'' . $column['name'] . '\')';
					$updateRule = 'Rule::unique(\'' . $tableName . '\', \'' . $column['name'] . '\')->ignore($this->' . $modelVariableName . '->getKey(), $this->' . $modelVariableName . '->getKeyName())';
					if ($hasSoftDelete && $column['unique_deleted_at_condition']) {
						$storeRule .= '->whereNull(\'deleted_at\')';
						$updateRule .= '->whereNull(\'deleted_at\')';
					}
					$serverStoreRules->push($storeRule);
					$serverUpdateRules->push($updateRule);
				}
			}

			switch ($column['type']) {
				case 'datetime':
					$serverStoreRules->push('\'date\'');
					$serverUpdateRules->push('\'date\'');
					$frontendRules->push('date_format:yyyy-MM-dd HH:mm:ss');
					break;
				case 'date':
					$serverStoreRules->push('\'date\'');
					$serverUpdateRules->push('\'date\'');
					$frontendRules->push('date_format:yyyy-MM-dd HH:mm:ss');
					break;
				case 'time':
					$serverStoreRules->push('\'date_format:H:i:s\'');
					$serverUpdateRules->push('\'date_format:H:i:s\'');
					$frontendRules->push('date_format:HH:mm:ss');
					break;

				case 'integer':
					$serverStoreRules->push('\'integer\'');
					$serverUpdateRules->push('\'integer\'');
					$frontendRules->push('integer');
					break;
				case 'tinyInteger':
					$serverStoreRules->push('\'integer\'');
					$serverUpdateRules->push('\'integer\'');
					$frontendRules->push('integer');
					break;
				case 'smallInteger':
					$serverStoreRules->push('\'integer\'');
					$serverUpdateRules->push('\'integer\'');
					$frontendRules->push('integer');
					break;
				case 'mediumInteger':
					$serverStoreRules->push('\'integer\'');
					$serverUpdateRules->push('\'integer\'');
					$frontendRules->push('integer');
					break;
				case 'bigInteger':
					$serverStoreRules->push('\'integer\'');
					$serverUpdateRules->push('\'integer\'');
					$frontendRules->push('integer');
					break;
				case 'unsignedInteger':
					$serverStoreRules->push('\'integer\'');
					$serverUpdateRules->push('\'integer\'');
					$frontendRules->push('integer');
					break;
				case 'unsignedTinyInteger':
					$serverStoreRules->push('\'integer\'');
					$serverUpdateRules->push('\'integer\'');
					$frontendRules->push('integer');
					break;
				case 'unsignedSmallInteger':
					$serverStoreRules->push('\'integer\'');
					$serverUpdateRules->push('\'integer\'');
					$frontendRules->push('integer');
					break;
				case 'unsignedMediumInteger':
					$serverStoreRules->push('\'integer\'');
					$serverUpdateRules->push('\'integer\'');
					$frontendRules->push('integer');
					break;
				case 'unsignedBigInteger':
					$serverStoreRules->push('\'integer\'');
					$serverUpdateRules->push('\'integer\'');
					$frontendRules->push('integer');
					break;

				case 'boolean':
					$serverStoreRules->push('\'boolean\'');
					$serverUpdateRules->push('\'boolean\'');
					$frontendRules->push('');
					break;
				case 'float':
					$serverStoreRules->push('\'numeric\'');
					$serverUpdateRules->push('\'numeric\'');
					$frontendRules->push('decimal');
					break;
				case 'decimal':
					$serverStoreRules->push('\'numeric\'');
					$serverUpdateRules->push('\'numeric\'');
					$frontendRules->push('decimal'); // FIXME?? I'm not sure about this one
					break;
				case 'string':
					$serverStoreRules->push('\'string\'');
					$serverUpdateRules->push('\'string\'');
					break;
				case 'text':
					$serverStoreRules->push('\'string\'');
					$serverUpdateRules->push('\'string\'');
					break;
				default:
					$serverStoreRules->push('\'string\'');
					$serverUpdateRules->push('\'string\'');
			}

			return [
				'name' => $column['name'],
				'type' => $column['type'],
				'serverStoreRules' => $serverStoreRules->toArray(),
				'serverUpdateRules' => $serverUpdateRules->toArray(),
				'frontendRules' => $frontendRules->toArray(),
			];
		});
	}
}
