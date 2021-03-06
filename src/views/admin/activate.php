<?php
namespace ellsif\WelCMS;
$data = $data ?? [];
?><!DOCTYPE html>
<html lang="ja-JP">
<head>
    <?php include dirname(__FILE__) . '/head.php' ?>
</head>
<body>
<div id="wrapper">
    <div id="page-wrapper" style="margin:0">
        <div class="row">
            <div class="col-lg-12">
                <h1 class="page-header">WelCMS初期設定</h1>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="panel panel-default">
                    <div class="panel-body">
                        <?php
                        echo  Form::formAlert(Validator::getErrorMessages($data));
                        ?>
                        <?php
                        echo Form::formStart(
                            '/welcms/activate', [],
                            [
                                'urlHome' => ['rule' => 'required', 'msg' => 'サイトURL : 必須入力です。'],
                                'siteName' => ['rule' => 'required', 'msg' => 'サイト名 : 必須入力です。'],
                                'adminID' => ['rule' => 'required', 'msg' => '管理者ID : 必須入力です。'],
                                'adminPass' => [
                                    ['rule' => 'required', 'msg' => '管理者パスワード : 必須入力です。'],
                                    ['rule' => 'length', 'args' => [12, 4], 'msg' => '管理者パスワード : 4文字以上、12文字以内で入力してください。']
                                ],
                            ]
                        );
                        ?>
                        <input type="hidden" name="Activated" value="1">
                        <?php
                        $port = intval($urlInfo['port']) !== 80 ? ':'.$urlInfo['port'] : '';
                        echo Form::formInput(
                            'サイトURL',
                            'urlHome',
                            [
                                'value' => $data['urlHome'][1] ?? $urlInfo['scheme'] . '://' . $urlInfo['host'] . $port . '/',
                                'placeholder' => 'https://example.com/',
                                'error' => $data['urlHome'][2] ?? '',
                            ]
                        );
                        echo Form::formInput(
                            'サイト名',
                            'siteName',
                            [
                                'placeholder' => 'WelCMS',
                                'value' => $data['siteName'][1] ?? '',
                                'error' => $data['siteName'][2] ?? '',
                            ]
                        );
                        echo Form::formInput(
                            '管理者ID',
                            'adminID',
                            [
                                'placeholder' => 'admin@example.com',
                                'help' => 'メールアドレス以外も設定可能です。',
                                'value' => $data['adminID'][1] ?? '',
                                'error' => $data['adminID'][2] ?? '',
                            ]
                        );
                        echo Form::formInput(
                            '管理者パスワード',
                            'adminPass',
                            [
                                'type' => 'password',
                                'help' => '4文字以上の半角英数記号のみ設定可能です。',
                                'value' => $data['adminPass'][1] ?? '',
                                'error' => $data['adminPass'][2] ?? '',
                            ]
                        );
                        ?>
                        <button type="submit" class="btn btn-default">これで設定する</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include dirname(__FILE__) . "/foot_js.php" ?>
</body>
</html>
