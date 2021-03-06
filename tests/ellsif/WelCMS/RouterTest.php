<?php
namespace ellsif\WelCms\Test;

use ellsif\WelCMS\Pocket;
use ellsif\WelCMS\Router;
use ellsif\WelCMS\WelUtil;


class RouterTest extends \PHPUnit\Framework\TestCase
{
    private $router;

    public static function setUpBeforeClass()
    {
        $config = Pocket::getInstance();
        $config->dbDriver('sqlite');
        $config->dbDatabase(dirname(__FILE__, 3) . '/data/RouterTest.sqlite');
        $config->dirSystem(dirname(__FILE__, 4) .  '/src');
        $config->dirApp(dirname(__FILE__, 2) . '/stub');
        require_once $config->dirApp() . '/classes/service/TestService.php';

        // ユーザーグループのテストデータ
        $userGroupEntity = WelUtil::getRepository('UserGroup');
        $userGroupEntity->save([
            [
                'name' => '所属するグループ',
                'userIDs' => '|1|',
            ]
        ]);

        // 個別ページのテストデータ
        $pageEntity = WelUtil::getRepository('Page');
        $pageEntity->save([
            [
                'templateID' => 1,
                'name' => 'Topページ',
                'path' => '',
                'published' => 1,
                'allowedUserGroupIds' => '',
            ],
            [
                'templateID' => 1,
                'name' => '下書きページ',
                'path' => 'hidden',
                'published' => 0,
                'allowedUserGroupIds' => '',
            ],
            [
                'templateID' => 1,
                'name' => '会員専用ページ',
                'path' => 'member',
                'published' => 1,
                'allowedUserGroupIds' => '|1|',
            ],
        ]);
    }

    public static function tearDownAfterClass()
    {
        unlink(dirname(__FILE__, 3) . '/data/RouterTest.sqlite');
    }

    protected function setUp()
    {
        $this->router = new Router();
        $_SERVER['REQUEST_URI'] = 'http://localhost.localdomain:8080/test/action/';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $config = Pocket::getInstance();
        $config->reset();
        $config->settingActivated(1);
        $config->dbDriver('sqlite');
        $config->dbDatabase(dirname(__FILE__, 3) . '/data/RouterTest.sqlite');
        $config->dirSystem(dirname(__FILE__, 4) .  '/src');
        $config->dirApp(dirname(__FILE__, 2) . '/stub/');
        $config->dirLog(dirname(__FILE__, 3) . '/logs/');
    }

    public function testGetInstance()
    {
        $this->assertInstanceOf(Router::class, $this->router);
    }

    /**
     * @depends testGetInstance
     */
    public function testInitialize()
    {
        $this->router->routing();

        $config = Pocket::getInstance();
        $this->assertEquals('GET', $config->varRequestMethod());
        $this->assertEquals('http://localhost.localdomain:8080/test/action/', $config->varCurrentUrl());
        $this->assertEquals('test/action', $config->varCurrentPath());
    }

    public function testRoutingActivate()
    {
        $config = Pocket::getInstance();
        $config->settingActivated(0);   // not activate

        $this->router->routing();

        $this->assertEquals('Admin', $config->varService());
        $this->assertEquals('activate', $config->varAction());
    }

    public function testRoutingActivate404()
    {
        $config = Pocket::getInstance();
        $config->settingActivated(0);   // not activate
        $_SERVER['REQUEST_URI'] = 'http://localhost.localdomain:8080/fabicon.ico';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(404);
        $this->router->routing();
    }

    public function testRouting404BadExtention()
    {
        $_SERVER['REQUEST_URI'] = 'http://localhost.localdomain:8080/fabicon.badext';

        $this->expectException(\InvalidArgumentException::class);
        $this->router->routing();
    }

    /**
     * ルーティング成功。
     *
     * ## 条件
     * - "/サービス/アクション/"形式のURLに対してルーティングを行う
     *
     * ## 結果
     * - Printer、Service，Actionが正しく設定される
     */
    public function testRoutingDefault()
    {
        $_SERVER['REQUEST_URI'] = 'http://localhost.localdomain:8080/test/action/';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->router->routing();

        $config = Pocket::getInstance();
        $this->assertEquals('\ellsif\WelCMS\Printer', $config->varPrinter());
        $this->assertEquals('test', $config->varService());
        $this->assertEquals('action', $config->varAction());
    }

    /**
     * パラメータ有りでルーティング成功。
     *
     * ## 条件
     * - "/サービス/アクション/パラメータ1/パラメータ2"形式のURLに対してルーティングを行う
     *
     * ## 結果
     * - Printer、Service，Actionが、ActionParamsが正しく設定される
     */
    public function testRoutingDefaultParam()
    {
        $_SERVER['REQUEST_URI'] = 'http://localhost.localdomain:8080/test/action/param1/100/param2/200/';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->router->routing();

        $config = Pocket::getInstance();
        $this->assertEquals('\ellsif\WelCMS\Printer', $config->varPrinter());
        $this->assertEquals('test', $config->varService());
        $this->assertEquals('action', $config->varAction());
        $this->assertEquals('["param1","100","param2","200"]', json_encode($config->varActionParams()));
    }

    /**
     * サブディレクトリを作った場合にルーティング成功。
     *
     * ## 条件
     * - serviceディレクトリにサブディレクトリを作成
     * - サブディレクトリ中にServiceクラスファイルを置く
     * - "/サブディレクトリ/サービス/アクション"形式のURLに対してルーティングを行う
     *
     * ## 結果
     * - Printer、Service，Actionが正しく設定される
     */
    public function testRoutingSubDir()
    {
        $_SERVER['REQUEST_URI'] = 'http://localhost.localdomain:8080/myserv/test/action';

        $this->router->routing();

        $config = Pocket::getInstance();
        $this->assertEquals('\ellsif\WelCMS\Printer', $config->varPrinter());
        $this->assertEquals('myserv/test', $config->varService());
        $this->assertEquals('action', $config->varAction());
    }

    public function testRoutingHidden()
    {
        $_SERVER['REQUEST_URI'] = 'http://localhost.localdomain:8080/hidden';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(404);
        $this->router->routing();
    }

    public function testRoutingPageAllowed()
    {
        $_SERVER['REQUEST_URI'] = 'http://localhost.localdomain:8080/member';
        $config = Pocket::getInstance();
        $config->loginUser(['id' => 1]);

        $this->router->routing();

        $this->assertEquals('\ellsif\WelCMS\PagePrinter', $config->varPrinter());
        $this->assertTrue(true);
    }

    public function testRoutingPageNotAllowed()
    {
        $_SERVER['REQUEST_URI'] = 'http://localhost.localdomain:8080/member';
        $config = Pocket::getInstance();
        $config->loginUser(['id' => 2]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(401);
        $this->router->routing();
    }

    /***********************************************************************
     * Auth関係のテスト
     ***********************************************************************/
    public function testRoutingUserSuccess()
    {
        $_SERVER['REQUEST_URI'] = 'http://localhost.localdomain:8080/test/auth1';
        $config = Pocket::getInstance();
        $config->loginUser(['id' => 1]);

        $this->router->routing();

        $this->assertEquals('auth1', $config->varAction());
        $this->assertEquals('auth1User', $config->varActionMethod());
    }

    /**
     * ユーザーログインが必要なページにログイン失敗。
     *
     * ## 条件
     * - ログインしていない
     * - ユーザーでのログインが必要なURLをリクエストする
     *
     * ## 結果
     * - ログイン画面にリダイレクトする
     */
    public function testRoutingUserFailure()
    {
        $this->assertTrue(false);   // TODO リダイレクトにしたのでGuzzleでのテストに変更する必要がある
        $_SERVER['REQUEST_URI'] = 'http://localhost.localdomain:8080/test/auth1';

        $this->expectExceptionCode(401);
        $this->router->routing();
    }

    public function testRoutingAdminSuccess()
    {
        $_SERVER['REQUEST_URI'] = 'http://localhost.localdomain:8080/test/auth2';
        $config = Pocket::getInstance();
        $config->isAdmin(true);

        $this->router->routing();
        $this->assertEquals('auth2', $config->varAction());
        $this->assertEquals('auth2Admin', $config->varActionMethod());
    }

    public function testRoutingAdminFailure()
    {
        $this->assertTrue(false);   // TODO リダイレクトにしたのでGuzzleでのテストに変更する必要がある

        $_SERVER['REQUEST_URI'] = 'http://localhost.localdomain:8080/test/auth2';
        $config = Pocket::getInstance();
        $config->loginUser(['id' => 1]);
        $config->loginManager(['id' => 1]);

        $this->expectExceptionCode(401);
        $this->router->routing();
    }

    public function testRoutingManagerSuccess()
    {
        $_SERVER['REQUEST_URI'] = 'http://localhost.localdomain:8080/test/auth3';
        $config = Pocket::getInstance();
        $config->loginManager(['id' => 1]);

        $this->router->routing();
        $this->assertEquals('auth3', $config->varAction());
        $this->assertEquals('auth3Manager', $config->varActionMethod());
    }

    public function testRoutingManagerFailure()
    {
        $this->assertTrue(false);   // TODO リダイレクトにしたのでGuzzleでのテストに変更する必要がある

        $_SERVER['REQUEST_URI'] = 'http://localhost.localdomain:8080/test/auth3';
        $config = Pocket::getInstance();
        $config->loginUser(['id' => 1]);

        $this->expectExceptionCode(401);
        $this->router->routing();
    }
}