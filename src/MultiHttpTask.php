<?php

/**
 * Class MultiHttpTask
 * Use select I/O model to execute concurrent curl tasks
 */


class MultiHttpTask {
	
	private $request_list = [];
	private $info_list = [];
	private $response_list = [];
	private $start_time;
	private $end_time;
	private $total_time = 0;
	private $is_complete = false;
    // curl multi handle
	private $mh;
    //curl easy handle集合
	private $ch = [];
	
	public function __construct(){
		//判断是否可以调用curl相关函数，否则报错
        if(!function_exists('curl_init') || !function_exists('curl_multi_init')){
            throw new BadFunctionCallException('curl methods are not supported');
        }
		$this->mh = curl_multi_init();
	}
	
	public function __destruct(){
		foreach($this->ch as $k => $v) {
			curl_multi_remove_handle($this->mh, $v);
			curl_close($v);
			unset($this->ch[$k]);
		}
		curl_multi_close($this->mh);
	}
	

    /**
     * @param array $request_info_array
     * @param null $task_name 定义任务别名,使用关联数组保存任务列表
     * @return $this
     */
	public  function addTask(array $request_info_array, $task_name = null){
		//判断传入数据格式是否正确
        if(isset($request_info_array['pattern']) && !in_array($request_info_array['pattern'],['form','json']) ){
            throw new InvalidArgumentException('unsupported params');
        }
		if(is_null($task_name)){
			array_push($this->request_list,$request_info_array);
		}else{
			$this->request_list[$task_name] = $request_info_array;
		}
		return $this;
	}

    /**
     * @param $task_id  此处task_id指的的存入request_list数组中的key值
     * @return $this
     */
	public  function removeTask($task_id){
	    if(isset($this->request_list[$task_id])){
            unset($this->request_list[$task_id]);
        }
		return $this;
	}

    /**
     * 批量执行任务，使用select阻塞当前进程
     * 任务结束之后设置相关结果变量
     */
	public  function execTask(){
		$mtime=explode(' ',microtime());
		$this->start_time=$mtime[1]+$mtime[0];
		
		foreach($this->request_list as $k => $v) {
			$is_post = strtolower($v['method']) == 'post' ? TRUE : FALSE ;
			$this->ch[$k] = $is_post ? $this->createPostHandle($v['url'],$v['params'],$v['pattern']) : $this->createGetHandle($v['url'],$v['params']);
			curl_multi_add_handle($this->mh, $this->ch[$k]);
		}

		do { //执行批处理句柄
			curl_multi_exec($this->mh, $running);
			curl_multi_select($this->mh); //阻塞直到cURL批处理连接中有活动连接,不加这个会导致CPU负载超过90%
		} while ($running > 0);
		
		foreach($this->ch as $k => $v) {
			$this->info_list[$k] = curl_getinfo($v);
			$this->response_list[$k] = curl_multi_getcontent($v);
		}
		$mtime=explode(' ',microtime());
		$this->end_time=$mtime[1]+$mtime[0];
		$this->total_time = $this->end_time - $this->start_time;
		$this->is_complete = true;

		return $this;
	}

    /**
     * 清空所有任务
     * @return $this
     */
	public  function resetTask(){
		//reset($this->request_list);
        $this->request_list = [];
		//如果任务已经完成，移除老的curl handle
		foreach($this->ch as $k => $v) {
			curl_multi_remove_handle($this->mh, $v);
		}
		$this->is_complete = false;
		return $this;
	}

    /**
     * 获取任务curl句柄信息
     * @param $task_id
     * @return mixed
     */
	public  function getTaskInfo($task_id){
		return $this->info_list[$task_id];
	}

    /**
     * 获取任务curl句柄
     * @param $task_id
     * @return mixed
     */
	public function getTaskHandle($task_id){
		return $this->ch[$task_id];
	}

    /**
     * 获取任务结果
     * @param $task_id
     * @return mixed
     */
	public  function getTaskResponse($task_id){
		return $this->response_list[$task_id];
	}

    /**
     * 获取当前任务列表
     * @return array
     */
	public function getCurrentTaskList(){
		return $this->request_list;
	}

    /**
     * 获取任务执行状态
     * @return bool
     */
	public function getTaskStatus(){
		return $this->is_complete;
	}

    /**
     * 获取批量任务执行总时间
     * @return int
     */
	public function getTaskExecTime(){
		return $this->total_time;
	}

    /**
     * 创建get请求句柄
     * @param $url
     * @param array $get_data
     * @param string $extra_header
     * @param int $connect_timeout_ms
     * @param int $timeout_ms
     * @return resource
     */
	private function createGetHandle($url, $get_data = [], $extra_header = '', $connect_timeout_ms = 0, $timeout_ms = 0)
	{
		$curl = curl_init();
		$query = is_array($get_data) && !empty($get_data) ? http_build_query($get_data) : $get_data;
		if (preg_match('/.*\?.*/',$url)) {
			$url = rtrim($url,"&").'&'.$query;
		} else {
			$url = rtrim($url,"&").'?'.$query;
		}
		curl_setopt($curl, CURLOPT_URL, $url);
        //CURLOPT_RETURNTRANSFER如果为0,这里会直接输出获取到的内容.如果为1,后面可以用curl_multi_getcontent获取内容
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_BINARYTRANSFER, 1);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, $connect_timeout_ms);
		curl_setopt($curl, CURLOPT_TIMEOUT, $timeout_ms);
		curl_setopt($curl,CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/
		   537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36");
		$headers = array();
		if (!empty($extra_header)) {
			array_push($headers, 'Content-Type: application/json; charset=utf-8');
			array_push($headers, $extra_header);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		}
		return $curl;
	}

    /**
     * 创建post请求句柄，只支持json，form格式
     * @param $url
     * @param $post_data
     * @param string $patten
     * @param string $extra_header
     * @param int $connect_timeout_ms
     * @param int $timeout_ms
     * @return bool|resource
     */
	private function createPostHandle($url, $post_data, $patten = 'json', $extra_header = '', $connect_timeout_ms = 0, $timeout_ms = 0){
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, $connect_timeout_ms);
		curl_setopt($curl, CURLOPT_TIMEOUT, $timeout_ms);

		switch ($patten) {
			case 'json':
				if (is_array($post_data)) {
					$post_string = json_encode($post_data, JSON_UNESCAPED_SLASHES);
				} else {
					$post_string = $post_data;
				}
				curl_setopt($curl, CURLOPT_POSTFIELDS, $post_string);
				$headers = array();
				array_push($headers, 'Content-Type: application/json; charset=utf-8');
				array_push($headers, 'Content-Length: ' . strlen($post_string));
				if (!empty($extra_header)) {
					array_push($headers, $extra_header);
				}

				curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
				break;
			case 'form':
				if (!is_array($post_data)) {
					return false;
				}
				$post_string = $post_data;
				curl_setopt($curl, CURLOPT_POSTFIELDS, $post_string);
				curl_setopt($curl, CURLOPT_HTTPHEADER,
					array('content-type: multipart/form-data; charset=utf-8')
				);
				break;
			default :
                throw new InvalidArgumentException("Request params $patten not supported");
		}
		return $curl;
	}
	
}
?>