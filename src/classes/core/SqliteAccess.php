<?php

namespace ellsif;

use ellsif\WelCMS\Exception;
use ellsif\WelCMS\Pocket;
use ellsif\WelCMS\WelUtil;
use \PDO;

class SqliteAccess extends DataAccess
{
    private $pdo = null;
    private $tables = [];

    /**
     * コンストラクタ。
     *
     * ## 説明
     * PDOの初期化を行います。
     *
     * ## 例外/エラー
     * PDOの初期化に失敗した場合、Exceptionをthrowします。
     */
    public function __construct()
    {
        $pocket = Pocket::getInstance();

        if ($pocket->dbPdo()) {
            $this->pdo = $pocket->dbPdo();
        } else {
            try {
                $this->pdo = new PDO(
                    'sqlite:' . $pocket->dbDatabase(),
                    $pocket->dbUsername(),
                    $pocket->dbPassword()
                );

            } catch (\PDOException $e) {
                throw new Exception('PDOの初期化に失敗しました。');
            }

            $this->pdo->setAttribute(PDO::ATTR_TIMEOUT, 20);  // ロックされている場合の解除待ち
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pocket->dbPdo($this->pdo);
        }
    }

    /**
     * テーブルを作成する
     *
     * @param string $name
     * @param array $columns
     * @return bool
     */
    public function createTable(string $name, array $columns) :bool
    {
        if (count($columns) == 0) {
            throw new Exception('テーブルの作成に失敗しました。カラムが指定されていません。');
        }
        if (array_key_exists('id', $columns) || array_key_exists('created', $columns) || array_key_exists('updated', $columns)) {
            throw new Exception('テーブルの作成に失敗しました。id, created, updated は自動的に追加されるため指定できません。');
        }
        $columnDefs = array(
            'id INTEGER PRIMARY KEY AUTOINCREMENT'
        );
        foreach($columns as $columnName => $array) {
            $columnName = $this->pdo->quote($columnName);
            $type = isset($array['type']) ? $this->convertType($array['type']) : 'TEXT';
            $default = '';
            if (isset($array['default'])) {
                $default = $type === 'TEXT' ? $this->pdo->quote($array['default']) : intval($array['default']);
                $default = "DEFAULT " . $default;
            }
            $columnDefs[] = "${columnName} ${type} ${default}";
        }
        $columnDefs[] = 'created TIMESTAMP';
        $columnDefs[] = 'updated TIMESTAMP';
        $sql = 'CREATE TABLE IF NOT EXISTS ' . $this->pdo->quote($name) . ' (' . implode(',', $columnDefs) . ')';
        $stmt = $this->pdo->prepare($sql);
        if ($stmt->execute()) {
            $this->tables(true); // テーブル一覧を更新する
            return true;
        }
        return false;

    }

    /**
     * テーブルを削除する
     *
     * @param string $name
     * @param bool $force trueの場合、WelCMS標準のテーブルも削除する
     * @return bool
     */
    public function deleteTable(string $name, bool $force = false) :bool
    {
        // TODO: Implement deleteTable() method.
    }

    /**
     * 件数を取得する。
     *
     * TODO filter未実装
     */
    public function count(string $name, array $filter = [])
    {
        if (!in_array($name, $this->tables())) {
            throw new \Exception("${name}テーブルは存在しません。", -1);
        }
        $sql = "SELECT COUNT(*) FROM " . $this->pdo->quote($name);
        list($whereSql, $values) = $this->createWhereSql($filter);
        if ($whereSql) {
            $sql .= ' WHERE ' . $whereSql;
        }
        $stmt = $this->pdo->prepare($sql);
        foreach($values as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        if ($stmt->execute()) {
            return $stmt->fetchColumn();
        } else {
            Logger::getInstance()->putLog('error', "DataAccess", "${name}からのデータの取得に失敗しました。" . $stmt->errorCode());
            throw new \Exception("${name}からのデータの取得に失敗しました。");
        }
    }

    /**
     * 1件取得する
     *
     * @param string $name
     * @param int $id
     */
    public function get(string $name, int $id)
    {
        $sql = "SELECT * FROM " . $this->pdo->quote($name) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id);
        if ($stmt->execute()) {
            $results = $stmt->fetchAll(PDO::FETCH_NAMED);
            if (count($results) > 0) {
                return $results[0];
            } else {
                return null;
            }
        } else {
            Logger::getInstance()->putLog('error', "DataAccess", "${name}からのデータの取得に失敗しました。" . $stmt->errorCode());
            throw new \Exception("${name}からのデータの取得に失敗しました。");
        }
    }

    /**
     * 複数件取得する
     *
     * @param string $name
     * @param int $offset
     * @param int $limit
     * @param string $order
     * @param array $options
     * @return array
     */
    public function select(string $name, int $offset = 0, int $limit = -1, string $order = '', array $filter = []) :array
    {
        if (!in_array($name, $this->tables())) {
            throw new \InvalidArgumentException("${name}テーブルは存在しません。", -1);
        }
        $sql = "SELECT * FROM " . $this->pdo->quote($name) . ' ';
        list($whereSql, $values) = $this->createWhereSql($filter, $order, $limit, $offset);
        if ($whereSql) {
            $sql .= ' WHERE ' . $whereSql;
        }

        if ($order) {
            $sql .= " ORDER BY ${order}";
        }
        // limitとoffset
        if (($limit = intval($limit)) > 0) {
            $sql .= " LIMIT ${limit}";
        }
        if (($offset = intval($offset)) > 0) {
            $sql .= " OFFSET ${offset}";
        }

        $stmt = $this->pdo->prepare($sql);
        foreach($values as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        if ($stmt->execute()) {
            $results = $stmt->fetchAll(PDO::FETCH_NAMED);
            Logger::getInstance()->putLog('debug', 'sql', WelUtil::getPdoDebug($stmt));
            Logger::getInstance()->putLog('debug', 'sql', "options: " . json_encode($filter));
            Logger::getInstance()->putLog('debug', 'sql', json_encode($results));
            return $results;
        } else {
            Logger::getInstance()->putLog('error', "DataAccess", "${name}からのデータの取得に失敗しました。" . $stmt->errorCode());
            throw new \RuntimeException("${name}からのデータの取得に失敗しました。");
        }
    }

    /**
     * SQL文を使って検索する。
     */
    public function selectQuery(string $sql, array $options = []) :array
    {
        $stmt = $this->pdo->prepare($sql);
        foreach($options as $key => $val) {
            if (is_string($key) && substr($key, 0, 1) !== ':') {
                $key = ':' . $key;
            }
            $stmt->bindValue($key, $val);
        }
        if ($stmt->execute()) {
            $results = $stmt->fetchAll(PDO::FETCH_NAMED);
            return $results;
        } else {
            Logger::getInstance()->putLog('error', "DataAccess", "データの取得に失敗しました。" . $stmt->errorCode());
            throw new \Exception("データの取得に失敗しました。");
        }
    }

    /**
     * 1件登録または更新する
     *
     * @param string $name
     * @param array $data
     * @return int
     */
    public function save(string $name, array $data) :int
    {
        // TODO: Implement save() method.
    }

    /**
     * 1件登録する
     *
     * @param string $name データ名（テーブル名やファイル名）
     * @param array $data 格納するデータ
     * @return int 登録データのid(失敗時は-1)
     */
    public function insert(string $name, array $data) :int
    {
        Logger::getInstance()->putLog('trace', 'DataAccess', "INSERT to ${name} start data:" . json_encode($data));

        $data = $this->addCreatedAt($data);

        $columns = array_keys($data);
        $params = [];
        foreach($columns as $column) {
            $params[] = ":${column}";
        }
        $sql = 'INSERT INTO ' . $this->pdo->quote($name) . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $params) . ')';
        $stmt = $this->pdo->prepare($sql);

        foreach($columns as $column) {
            $stmt->bindValue(":${column}", $data[$column]);
        }
        Logger::getInstance()->putLog('trace', 'DataAccess', WelUtil::getPdoDebug($stmt));
        try {
            $stmt->execute();
        } catch(\Exception $e) {
            $errorInfo = $stmt->errorInfo();
            Logger::getInstance()->putLog('error', 'DataAccess', $errorInfo[2]);
            throw new \Exception("${name}へのINSERTに失敗しました。", 0, $e);
        }
        $id = $this->pdo->lastInsertId();
        return $id;
    }

    /**
     * 1件更新する
     *
     * @param string $name データ名（テーブル名やファイル名）
     * @param int $id データのid
     * @param array $data
     * @return bool
     */
    public function update(string $name, int $id, array $data) :bool
    {
        Logger::getInstance()->putLog('trace', 'DataAccess', "UPDATE to ${name} data:" . json_encode($data));

        $data = $this->addUpdatedAt($data);

        list($columns, $params) = $this->parseConditions($data);
        $sql = 'UPDATE ' . $this->pdo->quote($name) . ' SET ';
        $sql .= implode(', ', $columns);
        $sql .= ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        foreach($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':id', $id);
        Logger::getInstance()->putLog('trace', 'DataAccess', WelUtil::getPdoDebug($stmt));
        if (!$stmt->execute()) {
            $errorInfo = $stmt->errorInfo();
            Logger::getInstance()->putLog('error', 'DataAccess', $errorInfo[2]);
            throw new \Exception("${name}のUPDATEに失敗しました。");
        }
        return $stmt->rowCount();
    }

    /**
     * 複数件更新する
     *
     * @param string $name
     * @param array $data
     * @param array $condition
     * @return int
     */
    public function updateAll(string $name, array $data, array $condition) :int
    {
        $data = $this->addUpdatedAt($data);

        list($dataColumns, $dataParams) = $this->parseConditions($data);
        list($whereColumns, $whereParams) = $this->parseConditions($condition, ':_');

        $sql = 'UPDATE ' . $this->pdo->quote($name) . ' SET ';
        $sql .= implode(', ', $dataColumns);
        $sql .= ' WHERE ' . implode(' AND ', $whereColumns);
        $stmt = $this->pdo->prepare($sql);

        foreach($dataParams as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        foreach($whereParams as $key => $val) {
            $stmt->bindValue($key, $val);
        }

        Logger::getInstance()->putLog('trace', 'DataAccess', WelUtil::getPdoDebug($stmt));
        if (!$stmt->execute()) {
            $errorInfo = $stmt->errorInfo();
            Logger::getInstance()->putLog('error', 'DataAccess', $errorInfo[2]);
            throw new \Exception("${name}のUPDATEに失敗しました。");
        }
        return $stmt->rowCount();
    }

    /**
     * 1件削除する
     *
     * @param string $name
     * @param int $id
     * @return bool
     */
    public function delete(string $name, int $id) :bool
    {
        $sql = 'DELETE FROM ' . $this->pdo->quote($name) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('id', $id);
        Logger::getInstance()->putLog('trace', 'DataAccess', WelUtil::getPdoDebug($stmt));
        if (!$stmt->execute()) {
            $errorInfo = $stmt->errorInfo();
            Logger::getInstance()->putLog('error', 'DataAccess', $errorInfo[2]);
            throw new \Exception("${name}のDELETEに失敗しました。");
        }
        return $stmt->rowCount();
    }

    /**
     * 複数件削除する
     *
     * @param string $name
     * @param array $condition
     * @return int
     */
    public function deleteAll(string $name, array $condition) :int
    {
        $sql = 'DELETE FROM ' . $this->pdo->quote($name) . ' WHERE ';
        list($columns, $params) = $this->parseConditions($condition);
        $sql .= implode(' AND ', $columns);
        $stmt = $this->pdo->prepare($sql);
        foreach($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }

        Logger::getInstance()->putLog('trace', 'DataAccess', WelUtil::getPdoDebug($stmt));
        if (!$stmt->execute()) {
            $errorInfo = $stmt->errorInfo();
            Logger::getInstance()->putLog('error', 'DataAccess', $errorInfo[2]);
            throw new \Exception("${name}のDELETEに失敗しました。");
        }
        return $stmt->rowCount();
    }

    /**
     * SQL文による更新・削除
     *
     * @param string $query
     * @return int
     */
    public function updateQuery(string $query) :int
    {
        // TODO: Implement updateQuery() method.
    }

    /**
     * テーブル名の一覧を取得する
     *
     * @return array
     */
    public function tables($force = false) :array
    {
        if ($force || empty($this->tables)) {
            $this->tables = [];
            $sql = "SELECT name FROM sqlite_master WHERE type = 'table'";
            $stmt = $this->pdo->prepare($sql);
            if ($stmt->execute()) {
                foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
                    $this->tables[] = $row->name;
                }
            }
        }
        return $this->tables;
    }

    /**
     * テーブルのカラム一覧を取得する
     *
     * ## 説明
     *
     */
    public function getColumns(string $name): array
    {
        $columns = [];
        $name = $this->pdo->quote($name);
        $sql = "PRAGMA table_info(${name})";
        $stmt = $this->pdo->prepare($sql);
        if ($stmt->execute()) {
            $results = $stmt->fetchAll(PDO::FETCH_OBJ);
            foreach ($results as $result) {
                $columns[$result->name] = [
                    'label' => $result->name,
                    'type' => $result->type,
                    'default' => $result->dflt_value,
                ];
                if (intval($result->notnull) > 0) {
                    $columns[$result->name]['validation'] = [
                        ['rule' => 'required'],
                    ];
                }
            }
        }
        return $columns;
    }

    /**
     * @param array $condition
     * @return array
     */
    private function parseConditions(array $condition, string $prefix = ':'): array
    {
        $columns = [];
        $values = [];
        foreach($condition as $key => $val) {
            $columns[] = "${key} = ${prefix}${key}";
            $values[$prefix . $key] = $val;
        }
        return [$columns, $values];
    }

    /**
     * created_atとupdated_atを追加する
     *
     * @param array $data
     * @return array
     */
    private function addCreatedAt(array $data) :array
    {
        if (!isset($data['created'])) {
            $data['created'] = time();
        }
        $data = $this->addUpdatedAt($data);
        return $data;
    }

    /**
     * updated_atを追加する
     *
     * @param array $data
     * @return array
     */
    private function addUpdatedAt(array $data) :array
    {
        if (!isset($data['updated'])) {
            $data['updated'] = time();
        }
        return $data;
    }

    public function convertType($type) :string
    {
        $conv = [
            'int'       => 'INTEGER',
            'float'     => 'REAL',
            'double'    => 'REAL',
            'string'    => 'TEXT',
            'text'      => 'TEXT',
            'timestamp' => 'TIMESTAMP',
        ];
        return $conv[$type] ?? 'TEXT';
    }

    /**
     * SQLのWHERE以降を生成します。
     */
    public function createWhereSql($filter): array
    {
        $whereSql = '';
        $columns = [];
        $values = [];
        foreach($filter as $key => $val) {
            if (is_array($val)) {
                // 配列はin句で処理
                $inColumns = [];
                foreach ($val as $idx => $_val) {
                    $inColumns[] = ":${key}_${idx}";
                    $values[":${key}_${idx}"] = $_val;
                }
                $columns[] = "${key} IN (" . implode(',', $inColumns) . ")";
            } elseif ($val === null) {
                $columns[] = "${key} IS NULL";
            } else {
                $columns[] = "${key} = :${key}";
                $values[":${key}"] = $val;
            }
        }

        if (count($columns) > 0) {
            $whereSql .= implode(' AND ', $columns);
        }
        return [$whereSql, $values];
    }
}