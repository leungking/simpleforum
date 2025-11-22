<?php
/**
 * @link http://simpleforum.org/
 * @copyright Copyright (c) 2015 SimpleForum
 * @author Jiandong Yu admin@simpleforum.org
 */

namespace app\install_update\models;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Model;
use app\components\Util;

/**
 * Database form
 */
class DatabaseForm extends Model
{
    public $host = 'localhost';
    public $port = 3306;
    public $dbname;
    public $username = 'root';
    public $password;
    public $tablePrefix = 'simple_';
    private $_dbConfig = [];

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['host', 'dbname', 'username', 'tablePrefix'], 'trim'],
            [['host', 'dbname', 'username'], 'required'],
            [['password'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'host' => Yii::t('app/admin', 'DB host'),
            'port' => Yii::t('app/admin', 'DB port'),
            'dbname' => Yii::t('app/admin', 'DB name'),
            'username' => Yii::t('app/admin', 'DB username'),
            'password' => Yii::t('app/admin', 'DB password'),
            'tablePrefix' => Yii::t('app/admin', 'Table prefix'),
        ];
    }

    public function checkDbInfo()
    {
        $port = (empty($this->port)||$this->port=='3306'?'':';port='.$this->port);
        $this->_dbConfig = [
            'dsn' => 'mysql:host='.$this->host. $port . ';dbname='.$this->dbname,
            'username' => $this->username,
            'password' => $this->password,
            'tablePrefix' => $this->tablePrefix,
            'charset' => 'utf8mb4',
        ];
        $db = new \yii\db\Connection($this->_dbConfig);
        $db->open();

        $versionString = $db->createCommand('SELECT VERSION()')->queryScalar();
        if ($versionString !== false) {
            $normalizedVersion = preg_replace('/[^0-9.]/', '', (string) $versionString);
            $normalizedVersion = $normalizedVersion === '' ? (string) $versionString : $normalizedVersion;

            if (version_compare($normalizedVersion, '5.7.0', '<')) {
                throw new InvalidConfigException(Yii::t('app/admin', 'MySQL 5.7.0 or higher is required.') . ' (' . $versionString . ')');
            }
        }

        return $db;
    }

    public function excuteSql($file)
    {
        $db = $this->checkDbInfo();
        $sql = file_get_contents($file);
        $sql = str_replace('simple_', $this->_dbConfig['tablePrefix'], $sql);
        $db->createCommand($sql)->execute();
    }

    public function createDbConfig() {
        $this->createConfigFile(Yii::getAlias('@app/config/db.php'), array_merge(['class'=>'yii\db\Connection', 'enableSchemaCache'=>true], $this->_dbConfig));
    }

    private function createConfigFile($file, $settings)
    {
        $config = '<?php'."\n";
        $config = $config. 'return ';
        $config = $config. Util::convertArrayToString($settings, '');
        $config = $config. ';'."\n";

        file_put_contents($file, $config);
    }

}
