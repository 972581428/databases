# thinkphp-databases
适用于thinkphp5.0小量数据库导入导出类库

## 配置
~~~php
// git databases存放至函数扩展目录extend中
~~~
![Image text](https://raw.githubusercontent.com/972581428/img-storage/master/imgs/111.png)
## 用法示例
使用 use databases\Databases;
在所需控制器内引用该扩展即可：
~~~php
<?php
namespace app\controller;
use databases\Databases;
class Index {
    protected $db_model,$datadir;
    public function _initialize()
    {
        $this->datadir = RUNTIME_PATH."backup/";//备份路径
        $this->db_model = new Databases($this->datadir); //实例化
    }
}
~~~
下面示例获取数据库下面数据表列表
~~~php
config('database.prefix') //数据库前缀
public function index() {
        $dataList = $this->db_model->db_list(config('database.prefix'));
        $total = 0;
        foreach ($dataList as $row){
            $total += $row['Data_length'];
        }
        $this->assign('totalSize', $total);
        $this->assign("dataList", $dataList);
        return $this->fetch();
    }
~~~
下面示例备份数据库
~~~php
public function backup(){
		$data = [
		    'tableid' => intval(input('get.tableid')), //默认 0
            'startfrom' => intval(input('get.startfrom')),//默认 0
            'sizelimit' => intval(input('param.sizelimit')), //分卷大小
            'volume' => intval(input('get.volume')) + 1,//默认 1
            'db_prefix' => config('database.prefix') //数据库前缀
        ];
		$tablesarr = input('post.tables/a'); //前台数据库表 array
        $filename = input('get.filename',''); //保存文件名称
		$result = $this->db_model->backup($data,$tablesarr,$filename);
        $this->success($result['msg']);
	}
~~~
下面示例恢复数据库列表
~~~php
public function recover(){
		$do = input("get.do"); //do[del：删除备份文件，import：导入备份]
		if ($do) {
            $files = input("post.files/a",[]); //删除文件名称
            $filename = input("get.filename",''); //备份文件名称
		    $result = $this->db_model->db_recover($do,$files,$filename);
		    $this->success($result['msg']);
		}
		//列出备份文件
		$filelist = dir_list($this->datadir);
		$files = [];
        foreach ((array)$filelist as $r) {
            $filename = explode('-', basename($r));
            $files[] = array('path' => $r, 'file' => basename($r), 'name' => $filename[0], 'size' => filesize($r), 'time' => filemtime($r));
        }
        rsort($files);
        $this->assign('files', $files);
		return $this->fetch();
	}
~~~
下面示例文件函数
~~~php
/**
 * [检查文件或者目录]
 * @param  [type] $path [文件路径]
 * @param  string $exts [类型]
 * @param  array  $list [返回数组]
 * @return [type]
 */
function dir_list($path, $exts = '', $d = '', $list= array()) {
    $path = dir_path($path);
    $files = glob($path.'*');
    foreach($files as $v) {
        $fileext = fileext($v);
        if (!$exts || preg_match("/\.($exts)/i", $v)) {
            $list[] = $v;
            if (is_dir($v)) {
                $list = dir_list($v, $exts, $list);
                if($d && count(scandir($v)) == 2){
                    @rmdir($v);
                }
            }
        }
    }
    return $list;
}

/**
 * [检查文件名称]
 * @param  [type] $filename [文件名称]
 * @return [type]
 */
function fileext($filename) {
    return strtolower(trim(substr(strrchr($filename, '.'), 1, 10)));
}

/**
 * [检查目录是否合法]
 * @param  [type] $path [目录路径]
 * @return [type]
 */
function dir_path($path) {
    $path = str_replace('\\', '/', $path);
    if(substr($path, -1) != '/') $path = $path.'/';
    return $path;
}
~~~

