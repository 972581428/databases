<?php
/**
 * 数据库控类库
 * @author      [我就叫小柯] <[972581428@qq.com]>
 * @copyright   Copyright (c) 2020 [环企优站科技]  (https://www.h7uz.com)
 * @version     v1.0
 */
namespace databases;
use think\Db;
class Databases{
    private $db = '';
    private $datadir = '';//数据库备份地址
    private $startrow = 0;
    private $startfrom = 0;
    private $complete = true;

    /**
     * 构造函数
     * @param string 公钥文件（验签和加密时传入）
     * @param string 私钥文件（签名和解密时传入）
     */
    public function __construct($dir){
        $this->db = Db::getConnection();
        $this->check_dir($dir);
    }


    protected function check_dir($datadir){
        if(!is_dir($datadir)){
            mkdir($datadir,0755,true);
        }
        $this->datadir = $datadir;
        return $this;
    }

    /**
     * 当前数据库表
     * @param $db_prefix 表前缀
     * @return array
     */
    public function db_list($db_prefix){
        return $this->db->query("SHOW TABLE STATUS LIKE '".$db_prefix."%'");
    }

    //导入数据库
    public function backup($data,$tabelsarr,$file){
        $tableid = $data['tableid'];
        $this->startfrom = $data['startfrom'];
        $sizelimit = $data['sizelimit']; //分卷大小
        $volume = $data['volume'];
        //查询
        $dataList = $this->db_list($data['db_prefix']);
        foreach ($dataList as $row){
            $table_info[$row['Name']] = $row;
        }
        $tables = cache('backuptables');
        if(empty($tabelsarr) && empty($tables)) {
            foreach ($dataList as $row){
                $tables[]= $row['Name'];
            }
        }else{
            $tables = [];
            if(!$tableid) {
                $tables = $tabelsarr;
                cache('backuptables',$tables);
            } else {
                $tables = cache('backuptables');
            }
            if( !is_array($tables) || empty($tables)) {
                return ['status'=>0,'msg'=>lang('do_empty')];
            }
        }
        unset($dataList);
        $sql = '';
        if(!$tableid) {
            $sql .= "-- Usezanphp SQL Backup\n-- Time:".toDate(time())."\n-- https://www.h7uz.com \n\n";
            foreach($tables as $key=>$table) {
                $sql .= "--\n-- Usezanphp Table `$table`\n-- \n";
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $info = $this->db->query("SHOW CREATE TABLE  $table");
                $sql .= str_replace(array('USING BTREE','ROW_FORMAT=DYNAMIC'),'',$info[0]['Create Table']).";\n";
            }
        }
        for(; $this->complete && $tableid < count($tables) && strlen($sql) + 500 < $sizelimit * 1000; $tableid++) {
            if($table_info[$tables[$tableid]]['Rows'] > 0){
                $sql .=  $this->dumptablesql($tables[$tableid], $this->startfrom, strlen($sql),$table_info[$tables[$tableid]]['Auto_increment']);
                if($this->complete) {
                    $this->startfrom = 0;
                }
            }
        }
        !$this->complete && $tableid--;
        $filename = htmlspecialchars(strip_tags($file));
        $filename = !$filename ? 'Usezan_'.rand_string(5).'_'.date('YmdH') : $filename;
        $filename_valume = sprintf($filename."-%s".'.sql', $volume);
        if(trim($sql)){
            $putfile = $this->datadir . $filename_valume;
            $r = file_put_contents($putfile , trim($sql));
        }
        if($tableid < count($tables) || $r){
            return ['status'=>200,'msg'=>"备份数据库".$filename_valume."成功"];
        }else{
            cache('backuptables',null);
            return ['status'=>200,'msg'=>lang('do_ok')];
        }
    }

    /***
     * 恢复-删除
     * @param $do
     * @param $files
     */
    public function db_recover($do,$files,$filename,$db_prefix=null){
        switch ($do) {
            case 'del':
                if (!empty($files) && is_array($files)) {
                    foreach ($files as $r){
                        @unlink($r);
                    }
                    return ['status'=>200,'msg'=>'删除数据库成功(ˇˍˇ)'];
                } else {
                    return ['status'=>200,'msg'=>'请选择要删除的数据库'];
                }
                break;
            case 'import':
                header('Content-Type:text/html;charset=UTF-8');
                $filelist = dir_list($this->datadir);
                foreach ((array)$filelist as $r){
                    $file = explode('-',basename($r));
                    if($file[0] == $filename){
                        $files[]  = $r;
                    }
                }
                $db_prefix = $db_prefix ? $db_prefix : config('database.prefix');
                foreach((array)$files as $file){
                    //读取数据文件
                    $sqldata = file_get_contents($file);
                    $sqlFormat = $this->sql_split($sqldata, $db_prefix);
                    foreach ((array)$sqlFormat as $sql){
                        $sql = trim($sql);
                        if (strstr($sql, 'CREATE TABLE')){
                            preg_match('/CREATE TABLE `([^ ]*)`/', $sql, $matches);
                            $ret =$this->excuteQuery($sql);
                        }else{
                            $ret = $this->excuteQuery($sql);
                        }
                    }
                    return ['status'=>200,'msg'=>'恢复数据库成功'];
                }
                break;
        }
    }

    /**
     * 数据库修复、分析
     * @param $tables
     * @param $do
     * @return array
     */
    public function docommand($tables,$do){
        if(empty($tables)) return ['status'=>0,'msg'=>'请勾选数据表!'];
        $tables = implode('`,`',$tables);
        $r = $this->db->query("{$do} TABLE `{$tables}`");
        if(false != $r){
            return ['status'=>200,'msg'=>$do.'数据表成功!'];
        }else{
            return ['status'=>0,'msg'=>$r['dberror']];
        }
    }

    /**
     * 下载
     * @param $filename
     */
    public function db_download($filename) {
        if (strstr($filename,'sql')) {
            $path = $this->datadir.$filename;
        } else {
            $path = $this->datadir.$filename.'.sql';
        }
        @header("Content-disposition:attachment;filename=".$filename);
        @header("Content-type:application/octet-stream");
        @header("Accept-Ranges: bytes");
        @header("Content-Length:".filesize($path));
        @header("Pragma:no-cache");
        @header("Expires:0");
        readfile($path);
    }

    /***
     * 恢复操作
     * @param string $sql
     * @return mixed
     */
    protected function excuteQuery($sql='') {
        if(empty($sql)) return false;
        $queryType = 'INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|LOAD DATA|SELECT .* INTO|COPY|ALTER|GRANT|TRUNCATE|REVOKE|LOCK|UNLOCK';
        if (preg_match('/^\s*"?(' . $queryType . ')\s+/i', $sql)) {
            $data['result'] = $this->db->execute($sql);
            $data['type'] = 'execute';
        }else {
            $data['result'] = $this->db->query($sql);
            $data['type'] = 'query';
        }
        return $data;
    }

    //组合、检查Sql 语句
    protected function dumptablesql($table, $startfrom = 0, $currsize = 0,$auto_increment=0) {
        $offset = 300;
        $insertsql = '';
        $sizelimit = intval(input('param.sizelimit'));
        $modelname = str_replace(config('database.prefix'),'',$table);
        $model = Db::name($modelname);
        $keyfield = $model->getPk();
        $rows = $offset;
        while($currsize + strlen($insertsql) + 500 < $sizelimit * 1000 && $rows == $offset) {
            if($auto_increment) {
                $selectsql = "SELECT * FROM $table WHERE $keyfield > $startfrom ORDER BY $keyfield LIMIT $offset";
            } else {
                $selectsql = "SELECT * FROM $table LIMIT $startfrom, $offset";
            }
            $tabledumped = 1;
            $row = $this->db->query($selectsql);
            $rows = count($row);
            foreach($row as $key=>$val) {
                foreach ($val as $k=>$field){
                    if(is_string($field)) {
                        $val[$k] = '\''. str_ireplace("'", "''", $field).'\''; //SQL指令安全过滤
                    } elseif($field === 0) {
                        $val[$k] = 0;
                    } elseif ($field == null){
                        $val[$k] = 'NULL';
                    }
                }
                if($currsize + strlen($insertsql) + 500 < $sizelimit * 1000) {
                    if($auto_increment) {
                        $startfrom = $row[$key][$keyfield];
                    } else {
                        $startfrom++;
                    }
                    $insertsql .= "INSERT INTO `$table` VALUES (".implode(',', $val).");\n";
                } else {
                    $this->complete = false;
                    break 2;
                }
            }
        }
        $this->startfrom = $startfrom;
        return $insertsql;
    }

    /**
     * [导入数据库]
     * @param  [type] $sql      [数据库]
     * @param  [type] $tablepre [数据表]
     * @return [type]
     */
    protected function sql_split($sql,$tablepre) {
        //检查表前缀
        if($tablepre != "usezan_") $sql = str_replace("usezan_", $tablepre, $sql);

        $sql = str_replace("\r", "\n", $sql);
        $ret = [];
        $num = 0;
        $queriesarray = explode(";\n", trim($sql));
        unset($sql);
        foreach($queriesarray as $query){
            $ret[$num] = '';
            $queries = explode("\n", trim($query));
            $queries = array_filter($queries);
            foreach($queries as $queryv){
                $str1 = substr($queryv, 0, 1);
                if($str1 != '#' && $str1 != '-') $ret[$num] .= $queryv;
            }
            $num++;
        }
        return $ret;
    }


}