<?php
/**
 * baserCMS :  Based Website Development Project <https://basercms.net>
 * Copyright (c) baserCMS User Community <https://basercms.net/community/>
 *
 * @copyright     Copyright (c) baserCMS User Community
 * @link          https://basercms.net baserCMS Project
 * @since         5.0.0
 * @license       http://basercms.net/license/index.html MIT License
 */

namespace BaserCore\Test\TestCase\Controller\Api;

use Cake\Core\Configure;
use BaserCore\Service\ContentService;
use Cake\TestSuite\IntegrationTestTrait;

class ContentsControllerTest extends \BaserCore\TestSuite\BcTestCase
{

    /**
     * IntegrationTestTrait
     */
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.BaserCore.Users',
        'plugin.BaserCore.UserGroups',
        'plugin.BaserCore.UsersUserGroups',
        'plugin.BaserCore.Contents',
        'plugin.BaserCore.ContentFolders',
        'plugin.BaserCore.Sites'
    ];

    /**
     * Access Token
     * @var string
     */
    public $accessToken = null;

    /**
     * Refresh Token
     * @var null
     */
    public $refreshToken = null;

    /**
     * set up
     */
    public function setUp(): void
    {
        parent::setUp();
        $token = $this->apiLoginAdmin(1);
        $this->accessToken = $token['access_token'];
        $this->refreshToken = $token['refresh_token'];
        $this->ContentService = new ContentService();
    }

    /**
     * Tear Down
     *
     * @return void
     */
    public function tearDown(): void
    {
        Configure::clear();
        parent::tearDown();
    }

    /**
     * testView
     *
     * @return void
     */
    public function testView(): void
    {
        $this->get('/baser/api/baser-core/contents/view/1.json?token=' . $this->accessToken);
        $this->assertResponseOk();
        $result = json_decode((string)$this->_response->getBody());
        $this->assertEquals('baserCMSサンプル', $result->content->title);
    }

    /**
     * testview_trash
     *
     * @return void
     */
    public function testView_trash(): void
    {
        $this->get('/baser/api/baser-core/contents/view_trash/16.json?token=' . $this->accessToken);
        $this->assertResponseOk();
        $result = json_decode((string)$this->_response->getBody());
        $this->assertEquals('削除済みフォルダー(親)', $result->trash->title);
    }
    /**
     * Test index method
     *
     * @return void
     */
    public function testIndex(): void
    {
        // indexテスト
        $this->get('/baser/api/baser-core/contents/index.json?token=' . $this->accessToken);
        $this->assertResponseOk();
        $result = json_decode((string)$this->_response->getBody());
        $this->assertEquals('', $result->contents[0]->name);
        // trashテスト
        $this->get('/baser/api/baser-core/contents/index/trash.json?token=' . $this->accessToken);
        $this->assertResponseOk();
        $result = json_decode((string)$this->_response->getBody());
        $this->assertNotNull($result->contents[0]->deleted_date);
        // treeテスト
        $this->get('/baser/api/baser-core/contents/index/tree.json?site_id=1&token=' . $this->accessToken);
        $this->assertResponseOk();
        // tableテスト
        $this->get('/baser/api/baser-core/contents/index/table.json?site_id=1&folder_id=6&name=サービス&type=Page&token=' . $this->accessToken);
        $this->assertResponseOk();
        $result = json_decode((string)$this->_response->getBody());
        $this->assertEquals(3, count($result->contents));
    }

    /**
     * Test delete method
     *
     * @return void
     */
    public function testDelete()
    {
        // 子要素を持たない場合
        $this->post('/baser/api/baser-core/contents/delete/4.json?token=' . $this->accessToken);
        $this->assertResponseOk();
        $result = json_decode((string)$this->_response->getBody());
        $this->assertEquals("コンテンツ: indexを削除しました。", $result->message);
        $this->get('/baser/api/baser-core/contents/view/4.json?token=' . $this->accessToken);
        $this->assertResponseError();
        // 子要素を持つ場合
        $this->post('/baser/api/baser-core/contents/delete/6.json?token=' . $this->accessToken);
        $this->assertResponseOk();
        $this->get('/baser/api/baser-core/contents/view/6.json?token=' . $this->accessToken); // 親要素削除チェック
        $this->assertResponseError();
        $this->get('/baser/api/baser-core/contents/view/11.json?token=' . $this->accessToken); // 子要素削除チェック
        $this->assertResponseError();
    }

    /**
     * Test delete method
     *
     * @return void
     */
    public function testDelete_trash()
    {
        $this->post('/baser/api/baser-core/contents/delete_trash/16.json?token=' . $this->accessToken);
        $this->assertResponseOk();
        $result = json_decode((string)$this->_response->getBody());
        $this->assertEquals("ゴミ箱: 削除済みフォルダー(親) を削除しました。", $result->message);
        $this->get('/baser/api/baser-core/contents/view_trash/16.json?token=' . $this->accessToken);
        $this->assertResponseError();
    }

    /**
     * testtrash_empty
     *
     * @return void
     */
    public function testTrash_empty()
    {
        $this->post('/baser/api/baser-core/contents/trash_empty.json?type=ContentFolder&token=' . $this->accessToken);
        $this->assertResponseOk();
        $result = json_decode((string)$this->_response->getBody());
        $this->assertEquals("ゴミ箱: 削除済みフォルダー(親)(ContentFolder)を削除しました。削除済みフォルダー(子)(ContentFolder)を削除しました。", $result->message);
        $this->get('/baser/api/baser-core/contents/index/trash.json?type=ContentFolder&token=' . $this->accessToken);
        $result = json_decode((string)$this->_response->getBody());
        $this->assertEmpty($result->contents);
    }

    /**
     * Test edit method
     *
     * @return void
     */
    public function testEdit()
    {
        $data = $this->ContentService->getIndex(['name' => 'testEdit'])->first();
        $id = $data->id;
        $data->name = 'ControllerEdit';
        $data->site->name = 'ucmitz'; // site側でエラーが出るため
        $this->post("/baser/api/baser-core/contents/edit/${id}.json?token=" . $this->accessToken, $data->toArray());
        $this->assertResponseSuccess();
        $query = $this->ContentService->getIndex(['name' => 'ControllerEdit']);
        $this->assertEquals(1, $query->count());
    }

    /**
     * testTrash_return
     *
     * @return void
     */
    public function testTrash_return()
    {
        $id = $this->ContentService->getTrashIndex()->first()->id;
        $this->get("/baser/api/baser-core/contents/trash_return/{$id}.json?token=" . $this->accessToken);
        $this->assertResponseOk();
        $this->assertNotEmpty($this->ContentService->get($id));
    }

    /**
     * testChange_status
     *
     * @return void
     */
    public function testChange_status()
    {
        $data = ['id' => 1, 'status' => 'unpublish'];
        $this->patch("/baser/api/baser-core/contents/change_status.json?token=" . $this->accessToken, $data);
        $this->assertResponseOk();
        $this->assertFalse($this->ContentService->get($data['id'])->status);
        $data = ['id' => 1, 'status' => 'publish'];
        $this->patch("/baser/api/baser-core/contents/change_status.json?token=" . $this->accessToken, $data);
        $this->assertResponseOk();
        $this->assertTrue($this->ContentService->get($data['id'])->status);
    }

    /**
     * testGet_full_url
     *
     * @return void
     */
    public function testGet_full_url()
    {
        $this->get("/baser/api/baser-core/contents/get_full_url/1.json?token=" . $this->accessToken);
        $this->assertResponseOk();
        $this->assertEquals("https://localhost/", json_decode((string)$this->_response->getBody())->fullUrl);
    }

    /**
     * testExists
     *
     * @return void
     */
    public function testExists()
    {
        $this->get("/baser/api/baser-core/contents/exists/1.json?token=" . $this->accessToken);
        $this->assertResponseOk();
        $this->assertTrue(json_decode($this->_response->getBody())->exists);
        $this->get("/baser/api/baser-core/contents/exists/100.json?token=" . $this->accessToken);
        $this->assertResponseOk();
        $this->assertFalse(json_decode($this->_response->getBody())->exists);
    }

    /**
     * リネーム
     *
     * 新規登録時の初回リネーム時は、name にも保存する
     */
    public function testRename()
    {
        $this->patch("/baser/api/baser-core/contents/rename.json?token=" . $this->accessToken);
        $this->assertResponseFailure();
        $data = ['id' => 1, 'title' => 'testRename'];
        $this->patch("/baser/api/baser-core/contents/rename.json?token=" . $this->accessToken, $data);
        $this->assertResponseOk();
        $this->assertStringContainsString('testRename', json_decode($this->_response->getBody())->message);
        $this->assertNotNull(json_decode($this->_response->getBody())->url);
    }

    /**
     * testGet_content_folder_list
     *
     * @return void
     */
    public function testGet_content_folder_list()
    {
        $this->get("/baser/api/baser-core/contents/get_content_folder_list/1.json?token=" . $this->accessToken);
        $this->assertResponseOk();
        $this->assertNotEmpty(json_decode($this->_response->getBody())->list);
    }

    /**
     * testGet_content_folder_list
     *
     * @return void
     */
    public function testExists_content_by_url()
    {
        $this->markTestIncomplete('このテストは、まだ実装されていません。');
        $this->get("/baser/api/baser-core/contents/exists_content_by_url.json?token=" . $this->accessToken);
        $this->assertResponseOk();
    }
}
