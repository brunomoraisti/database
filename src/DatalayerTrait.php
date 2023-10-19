<?php
namespace BMorais\Database;

/**
 * CLASS TRAIT DATALAYER
 * This class of execution methods in the database
 *
 * @author Bruno Morais <brunomoraisti@gmail.com>
 * @copyright MIT, bmorais.com
 * @package bmorais\database
 * @subpackage class
 * @access private
 */

use PDO;
use PDOException;
use PDOStatement;
use stdClass;

trait DatalayerTrait
{
    /** @var PDO|null */
    protected $instance = null;

    /** @var array */
    protected $fields;

    /** @var PDOStatement|false */
    protected $prepare = null;

    /** @var string */
    protected $database = CONFIG_DATA_LAYER["dbname"];

    /** @var string */
    protected $classModel;

    /** @var string */
    protected $tableName;

    /** @var array */
    protected $resultArray = array();

    /** @var string */
    private $logSQL;

    /**
     * @return PDO|false
     */
    private function getInstance()
    {
        try {
            if (strpos($_SERVER['SERVER_NAME'], mb_strtolower(CONFIG_DATA_LAYER["homologation"])) && !strpos($this->database, ucfirst(CONFIG_DATA_LAYER["homologation"]))) {
                $this->database .= ucfirst(CONFIG_DATA_LAYER["homologation"] ?? "");
            }

            if (empty($this->instance)) {
                $this->instance = new PDO(
                    CONFIG_DATA_LAYER['driver'] . ':host=' . CONFIG_DATA_LAYER['host'] . ';dbname=' . $this->database . ';port=' . CONFIG_DATA_LAYER['port'],
                    CONFIG_DATA_LAYER['username'],
                    CONFIG_DATA_LAYER['passwd'],
                    CONFIG_DATA_LAYER['options']
                );
            }

            return $this->instance;
        } catch (PDOException $e) {
            $this->setError($e);
            return false;
        }

    }

    /**
     * @param String $query
     * @param array|null $params
     * @return false|mixed|\PDOStatement|null
     */
    protected function executeSQL(String $query, ?array $params = null)
    {
        try {
            $this->prepare = $this->getInstance()->prepare($query);
            $this->prepare->execute($params);
            $this->setLogSQL($query, $params);
            return $this->prepare;
        } catch (PDOException $e) {
            $this->setError($e, $query);
            return false;
        }
    }

    /**
     * @param $prepare
     * @return int|false
     */
    protected function count($prepare=null): ?int
    {
        try {
            $prepare = empty($prepare) ? $this->prepare : $prepare;
            $qtd = $prepare->rowCount();
            return $qtd;
        } catch (PDOException $e) {
            $this->setError($e);
            return false;
        }


    }

    /**
     * @param $prepare
     * @return array|false
     */
    protected function fetchArrayAssoc($prepare=null): ?array
    {
        try {
            $prepare = empty($prepare) ? $this->prepare : $prepare;
            $dados = $prepare->fetchAll(PDO::FETCH_ASSOC);
            $this->resultArray = $dados;
            return $dados;
        } catch (PDOException $e) {
            $this->setError($e);
            return null;
        }
    }

    /**
     * @param $prepare
     * @return array|false
     */
    protected function fetchArrayObj($prepare=null): ?array
    {
        try {
            $prepare = empty($prepare) ? $this->prepare : $prepare;
            $dados = $prepare->fetchAll(PDO::FETCH_OBJ);
            $this->resultArray = $dados;
            return $dados;
        } catch (PDOException $e) {
            $this->setError($e);
            return null;
        }
    }

    /**
     * @param $prepare
     * @param String|null $class
     * @return array|false
     */
    protected function fetchArrayClass($prepare=null, String $class=null): ?array
    {
        try {
            $prepare = empty($prepare) ? $this->prepare : $prepare;
            $class = empty($class) ? $this->classModel : $class;
            $dados = $prepare->fetchAll(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, CONFIG_DATA_LAYER["directory_models"] . $class);
            $this->resultArray = $dados;
            return $dados;
        } catch (PDOException $e) {
            $this->setError($e);
            return null;
        }
    }

    /**
     * @param $prepare
     * @return array|false
     */
    protected function fetchOneAssoc($prepare=null): ?array
    {
        try {
            $prepare = empty($prepare) ? $this->prepare : $prepare;
            $dados = $prepare->fetch(PDO::FETCH_ASSOC);
            return $dados;
        } catch (PDOException $e) {
            $this->setError($e);
            return null;
        }
    }

    /**
     * @param $prepare
     * @return stdClass|false
     */
    protected function fetchOneObj($prepare=null): ?stdClass
    {
        try {
            $prepare = empty($prepare) ? $this->prepare : $prepare;
            $dados = $prepare->fetch(PDO::FETCH_OBJ);
            return $dados;
        } catch (PDOException $e) {
            $this->setError($e);
            return null;
        }
    }

    /**
     * @param $prepare
     * @param String|null $class
     * @return array|false
     */
    protected function fetchOneClass($prepare=null, String $class=null): ?object
    {
        try {
            $prepare = empty($prepare) ? $this->prepare : $prepare;
            $class = empty($class) ? $this->classModel : $class;
            $dados = $prepare->fetchObject(CONFIG_DATA_LAYER["directory_models"] . $class);
            return $dados;
        } catch (PDOException $e) {
            $this->setError($e);
            return null;
        }
    }

    /**
     * @return bool
     */
    protected function beginTrasaction(): ?bool
    {
        try {
            $this->getInstance()->beginTransaction();
            return true;
        } catch (PDOException $e) {
            $this->setError($e);
            return null;
        }

    }

    /**
     * @return bool
     */
    protected function commitTransaction(): ?bool
    {
        try {
            $this->getInstance()->commit();
            return true;
        } catch (PDOException $e) {
            $this->setError($e);
            return null;
        }
    }

    /**
     * @return bool
     */
    protected function rollBackTransaction(): ?bool
    {

        try {
            $this->getInstance()->rollBack();
            return true;
        } catch (PDOException $e) {
            $this->setError($e);
            return null;
        }
    }

    /**
     * @return string|null
     */
    private function lastId(): ?string
    {
         try {
             $ultimo = $this->getInstance()->lastInsertId();
             return $ultimo;
         } catch (PDOException $e)
         {
             $this->setError($e);
             return null;
         }
    }

    /**
     * @param $sql_string
     * @param array|null $params
     * @return void
     */
    private function setLogSQL($sql_string, array $params = null)
    {
        if (!empty($params)) {
            $indexed = $params == array_values($params);
            foreach($params as $k=>$v) {
                if (is_object($v)) {
                    if ($v instanceof \DateTime) {
                        $v = $v->format('Y-m-d H:i:s');
                    } else {
                        continue;
                    }
                } elseif (is_string($v)) {
                    $v="'$v'";
                } elseif ($v === null) {
                    $v='NULL';
                } elseif (is_array($v)) {
                    $v = implode(',', $v);
                }

                if ($indexed) {
                    $sql_string = preg_replace('/\?/', $v, $sql_string, 1);
                } else {
                    if ($k[0] != ':') {
                        $k = ':'.$k;
                    } //add leading colon if it was left out
                    $sql_string = str_replace($k, $v, $sql_string);
                }
            }
        }
        $this->logSQL = $sql_string;
    }

    /**
     * @param PDOException $e
     * @param string $sql
     * @return void
     */
    private static function setError(PDOException $e, string $sql = '')
    {
        self::$error = $e;
        if (CONFIG_DATA_LAYER['return_error_json']) {
            $obj = [
                'error' => true,
                'message' => 'Ooops! ERRO',
                'code' => '500',
            ];

            if (CONFIG_DATA_LAYER['display_errors_details'] ?? true) {
                $obj['sql'] = $sql;
                $obj['exception'] = $e;
            }

            echo json_encode($obj);
        } else {
            $message = '<h4>Ooops! ERRO</h5><hr>';
            $message .= '<p><b>File:</b>  ' . $e->getFile() . '<br/>';
            $message .= '<b>SQL:</b>  ' . $sql . '<br/>';
            $message .= '<b>Line:</b>  ' . $e->getLine() . '<br/>';
            $message .= '<b>Message:</b>  ' . $e->getMessage() . '<br/>';
            $message .= '<b>Exception:</b>' . $e->getCode() . '<br/>' . $e->getPrevious() . '<br/>' . $e->getTraceAsString() . '<br/></p>';

            if (CONFIG_DATA_LAYER['display_errors_details']) {
                echo $message;
            } else {
                echo '<h4>Ooops! ERRO</h5><hr>';
            }
        }
    }
}
