<?php

namespace Tests\Fixtures;

use Dotenv\Dotenv;
use PDO;

trait DbFixture
{
    protected function getTestDatabase(): PDO
    {

        //forçamos recarregar as variaveis de ambiente
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../../store');
        $dotenv->safeLoad();

        $host = $_ENV['MYSQL_HOST'] ?: '';
        $port = $_ENV['MYSQL_PORT'] ?: '';
        $dbname = $_ENV['MYSQL_DATABASE'] ?: '';
        $testDbName = "$dbname-test";
        $userroot = "root";
        $passroot = $_ENV['MYSQL_ROOT_PASSWORD'] ?: '';
        $userDefault = $_ENV['MYSQL_USER'] ?: '';
        $passDefault = $_ENV['MYSQL_PASSWORD'] ?: '';

        //arquivo do banco
        $initSqlPath = '../docker/dev/db/init.sql';
        if (!file_exists($initSqlPath)) {
            $this->fail("O arquivo de esquema do banco de dados não foi encontrado em: " . $initSqlPath);
        }
        $sqlTemplate = file_get_contents($initSqlPath);
        $sqlTemplate = str_replace($dbname, $testDbName, $sqlTemplate);
        //arquivo do banco

        // Conexão para criação do banco test
        $pdo = new PDO("mysql:host=$host:$port", $userroot, $passroot);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        //criação do banco test
        $pdo->exec("DROP DATABASE IF EXISTS `$testDbName`;");
        $pdo->exec("GRANT ALL PRIVILEGES ON `$testDbName`.* TO '$userDefault'@'%'");
        $pdo->exec("FLUSH PRIVILEGES;");
        $pdo->exec($sqlTemplate);

        //conexão default
        $pdo = new PDO("mysql:host=$host:$port;dbname=$testDbName", $userDefault, $passDefault);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }
}