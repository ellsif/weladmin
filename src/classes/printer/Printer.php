<?php

namespace ellsif\WelCMS;

/**
 * 汎用Printerクラス。
 *
 * ## 説明
 *
 */
class Printer
{

    /**
     * Htmlを表示する。
     *
     *
     */
    public function html(ServiceResult $result = null)
    {
        $viewPath = null;
        if ($result && $result->getView('html')) {
            // viewの指定があれば表示
            $viewPath = $result->getView('html');
        } else {
            $viewPath = Router::getViewPath($viewPath);
        }
        $data = $result ? $result->resultData() : [];
        if ($result->isError() && !isset($data['errors'])) {
            $data['errors'] = $result->error();
        }
        WelUtil::loadView($viewPath, $data);
    }

    /**
     * jsonを表示する。
     */
    public function json(ServiceResult $result)
    {
        header("Content-Type: application/json; charset=utf-8");
        if ($result->isError()) {
            http_response_code(500);
        }
        if ($result->getView('json')) {
            WelUtil::loadView($result->getView('json'), $result->resultData());
        } else {
            echo json_encode(["result" =>$result->resultData()]);
        }
    }

    /**
     * CSVを表示する。
     */
    public function csv(ServiceResult $result)
    {
        if ($result->isError()) {
            http_response_code(500);
            exit;
        }
        $resultData = $result->resultData();
        $fileName = $resultData['fileName'] ?? 'sample';
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=${fileName}.csv");
        header("Content-Transfer-Encoding: binary");

        $output = fopen('php://output', 'w');
        $indent = [];
        $recursive = function($out, $data) use(&$recursive, &$indent) {
            if (\ellsif\isArray($data)) { // 連番の配列
                foreach($data as $row) {
                    if (\ellsif\isObjectArray($row)) {
                        $indent[] = '';
                        $recursive($out, $row);
                    } elseif(is_array($row)) {
                        fputcsv($out, array_merge($indent, $row));
                    } else {
                        fputcsv($out, array_merge($indent, [$row]));
                    }
                }
            } elseif (is_array($data)) {  // 連想配列
                foreach($data as $key => $row) {
                    if (\ellsif\isObjectArray($row)) {
                        fputcsv($out, array_merge($indent, [$key]));
                        $indent[] = '';
                        $recursive($out, array_merge($indent, $row));
                    } elseif(\ellsif\isArray($row)) {
                        fputcsv($out, array_merge($indent, $row));
                    } elseif (is_object($row)) {
                        $indent[] = '';
                        $recursive($out, $row);
                    } else {
                        fputcsv($out, array_merge($indent, [$key, $row]));
                    }
                }
            } elseif (is_object($data)) {
                $recursive($out, json_decode(json_encode($data), true));
            }
        };
        $recursive($output, $resultData);
        fclose($output);
    }

    /**
     * XMLを表示する。
     */
    public function xml($result)
    {

    }

    /**
     * SVGを表示する。
     */
    public function svg($result)
    {

    }
}