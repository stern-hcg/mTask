<?php
/**
 * Created by PhpStorm.
 * User: stern
 * Date: 2017/9/27
 * Time: 16:32
 */

class TestMultiHttpTask extends PHPUnit_Framework_TestCase
{
    private $temp_url = 'https://tmoses.ofashion.com.cn:8080/internal_service/get_trade_list?no_check=1&type=6&seller_uid=97947&collection_id=7';
    private $another_url = 'http://116.62.132.116:86/trade/get_order_list';
    private $param_array = ['seller_uid'=>97947, 'start'=> 100, 'count'=> 1];
    private $request;
    private $wrong_request;

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->request = [
            'req0' => ['url' => $this->another_url,'method' => 'post', 'params' => $this->param_array,'pattern' => 'json'],
            'req1' => ['url' => $this->temp_url,'method' => 'get'],
            ['url' => $this->temp_url.'&task=1','method' => 'get']
        ];
        $this->wrong_request = [
            'req0' => ['url' => $this->another_url,'method' => 'post', 'params' => $this->param_array,'pattern' => 'wrong_pattern' ]
        ];
    }

    public function objectProvider(){
        $demoMultiTask = new MultiHttpTask();
        return $demoMultiTask;
    }

    /**
     *
     * @return MultiHttpTask
     */
    public function testAddAndRemoveTask()
    {
        //测试添加任务：队列已有任务，队列无任务
        //测试删除任务：队列无任务，队列有任务 | 提供正确的任务名或id，提供错误的任务名或id

        $demoMultiTask = new MultiHttpTask();
        self::assertEmpty($demoMultiTask->getCurrentTaskList(),'初始任务列表非空');
        $demoMultiTask->removeTask('req_not_exist');
        self::assertEmpty($demoMultiTask->getCurrentTaskList(),'移除不存在元素，初始任务列表非空');
        foreach ($this->request as $name => $value){
            $demoMultiTask->addTask($value,$name);
        }
        foreach ($this->request as $name => $value) {
            self::assertArrayHasKey($name, $demoMultiTask->getCurrentTaskList(),"任务：$name 不存在！");
        }
        self::assertEquals(3, count($demoMultiTask->getCurrentTaskList()));
        $demoMultiTask->removeTask('req1');

        self::assertEquals(2, count($demoMultiTask->getCurrentTaskList()));
        $demoMultiTask->removeTask('req_not_exist');
        self::assertEquals(2, count($demoMultiTask->getCurrentTaskList()));
        self::assertArrayNotHasKey('req1', $demoMultiTask->getCurrentTaskList(),"任务：req1 删除后仍然存在！");

        return $demoMultiTask;
    }

    /**
     * @param MultiHttpTask $demoMultiTask
     * @depends testAddAndRemoveTask
     */
    public function testExecTask(MultiHttpTask $demoMultiTask){
        //todo 测试任务队列为0,1,2的情况
        $demoMultiTask->execTask();
        self::assertTrue($demoMultiTask->getTaskStatus(),'task status not correct!');

        foreach ($demoMultiTask->getCurrentTaskList() as $name => $value){
            self::assertNotEmpty($demoMultiTask->getTaskInfo($name),'curl task info not exist!');
            //self::assertNotEmpty($demoMultiTask->getTaskResponse($name),'curl task response not exist!');
            self::assertNotNull($demoMultiTask->getTaskResponse($name),'curl task response not exist!');
            self::assertNotEmpty($demoMultiTask->getTaskHandle($name),'curl task handle not exist!');
        }

        return $demoMultiTask;
    }


    /**
     * @param MultiHttpTask $demoMultiTask
     * @depends testExecTask
     */
    public function testResetTask(MultiHttpTask $demoMultiTask){
        //测试任务队列为空，不为空 | 执行，未执行 总共四种组合情况
        $demoMultiTask->resetTask();
        self::assertEmpty($demoMultiTask->getCurrentTaskList());
        self::assertFalse($demoMultiTask->getTaskStatus());
        return $demoMultiTask;
    }


    /**
     * @param MultiHttpTask $demoMultiTask
     * @depends testExecTask
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessageRegExp #.*not.*supported.*#
     * RegExp = .*not.*supported.*
     */
    public function testException(MultiHttpTask $demoMultiTask){
        foreach ($this->wrong_request as $name => $value){
            $demoMultiTask->addTask($value,$name);
        }
    }

    /**
     * @param MultiHttpTask $demoMultiTask
     * @depends testResetTask
     */
//    public function testException2(MultiHttpTask $demoMultiTask){
//        //$this->setExpectedExceptionRegExp('InvalidArgumentException', '/.*not.*supported.*/', 10);
//        $this->setExpectedExceptionRegExp('InvalidArgumentException', '/.*not.*supported.*/');
//        foreach ($this->wrong_request as $name => $value){
//            $demoMultiTask->addTask($value,$name);
//        }
//    }
}
?>