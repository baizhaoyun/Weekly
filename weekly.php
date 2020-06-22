<?php
namespace Awesome;


use GuzzleHttp\Client;
use Awesome\Exception\WeeklyException;

class Weekly
{
    const MUST_FIELDS = [
        'mail_address',
        'mail_password',
        'mail_name',
        'mail_host',
        'mail_port',
        'mail_to',
        'jira_base_uri',
        'oa_username',
        'oa_password',
    ];

    public $config = [];

    public function __construct(array $config)
    {
        foreach (self::MUST_FIELDS as $value) {
            if (empty($config[$value])) {
                throw new WeeklyException("empty {$value}, please set in config array.");
            } else {
                $this->config[$value] = $config[$value];
            }
        }
    }

    public function run()
    {
        $weeklyData = json_decode($this->getWeeklyData(), true);
        
        if (!$weeklyData) {
            throw new WeeklyException('you did not do anything this week, please check yourself.');
        }


        $jiraData = $this->_weeklyToJira($weeklyData);

        $transport = (new \Swift_SmtpTransport($this->config['mail_host'], $this->config['mail_port']))
            ->setUsername($this->config['mail_address'])
            ->setPassword($this->config['mail_password']);

        $mailer = new \Swift_Mailer($transport);

        $message = (new \Swift_Message())
            ->setSubject('This is your your weekly report')
            ->setFrom([$this->config['mail_address'] => $this->config['mail_name']])
            ->setTo([$this->config['mail_to']])
            ->setContentType('text/html')
            ->setBody($this->_getEmailBody($jiraData));

        $mailer->send($message);
    }
    
    private function _weeklyToJira(array $weeklyData)
    {
        $jiraData = [];

        if ($weeklyData['issues'] && is_array($weeklyData['issues'])) {
            foreach ($weeklyData['issues'] as $item) {
                $jiraData[] = [
                    'summary' => $item['fields']['summary'],
                    'status_desc' => $item['fields']['status']['name'],
                    'develop_time' => $item['fields']['customfield_10341'],
                    'test_time' => $item['fields']['customfield_10346'],
                    'url' => $this->config['jira_base_uri'] . 'browse/' . $item['key']
                ];
            }
        }
        return $jiraData;
    }

    private function _getEmailBody($jiraData)
    {
        if (empty($jiraData)) {
            return '';
        }

        $count = count($jiraData);
        $first = true;
        $content = '';
        foreach ($jiraData as $data) {
            $developTime = $data['develop_time'] ? date('Y/m/d', strtotime($data['develop_time'])) : '';
            $testTime = $data['test_time'] ? date('Y/m/d', strtotime($data['test_time'])) : '';

            $url = str_replace('\r\n', '', $data['url']);
            $content .= "<tr>";
            if ($first) {
                $content .= "<td rowspan={$count}>本周开发项目</td>";
                $first = false;
            }

            $content .= "<td>{$data['summary']}</td>";
            $content .= "<td>{$data['status_desc']}</td>";
            $content .= "<td>{$developTime}</td>";
            $content .= "<td>{$testTime}</td>";
            $content .= "<td>{$url}</td>";
            $content .= "<td></td>";
            $content .= "</tr>";
        }
        //var_dump($jiraData);exit;

        $body = <<<EOT
<!DOCTYPE html>
<html>
<head>
	<title></title>

	<style type="text/css">
table.gridtable {
	font-family: "微软雅黑";
	font-size:14px;
	color:#333333;
	border-width: 1px;
	border-color: #666666;
	border-collapse: collapse;
	width: 1600px;
}
table.gridtable th {
	border-width: 1px;
	padding: 8px;
	border-style: solid;
	border-color: #666666;
	background-color: #C4D79B;
	height: 20px;
}
table.gridtable td {
	border-width: 1px;
	padding: 8px;
	border-style: solid;
	border-color: #666666;
	background-color: #ffffff;
	height: 20px;
}
table.categroy {
	width: 20px;
}
</style>
</head>
<body>

<!-- Table goes in the document BODY -->
<table class="gridtable">
<tr>
	<th>类别</th>
	<th>项目</th>
	<th>状态</th>
	<th>开始开发时间</th>
	<th>实际提测时间</th>
	<th>jira</th>
	<th>备注</th>
</tr>

{$content}

<tr>
	<th>类别</th>
	<th>项目</th>
	<th colspan=4>jira</th>
	<th>备注</th>
</tr>

<tr>
    <td rowspan="3">下周工作计划</td>
    <td></td>
    <td colspan=4></td>
    <td></td>
</tr>
<tr>
    <td></td>
    <td colspan=4></td>
    <td></td>
</tr>
<tr>
    <td></td>
    <td colspan=4></td>
    <td></td>
</tr>

</table>
</body>
</html>
EOT;
        //var_dump($body);exit;
        return $body;
    }

    private function getWeeklyData()
    {
        $jql = '(%E5%AE%9E%E9%99%85%E4%B8%8A%E7%BA%BF%E6%97%B6%E9%97%B4%20%3E%3D%20startOfWeek()%20OR%20%E5%AE%9E%E9%99%85%E5%BC%80%E5%A7%8B%E5%BC%80%E5%8F%91%E6%97%B6%E9%97%B4%3E%3D%20startOfWeek()%20OR%20%E5%AE%9E%E9%99%85%E6%8F%90%E6%B5%8B%E6%97%B6%E9%97%B4%20%3E%3D%20startOfWeek()%20OR%20status%20%3D%20%E5%BC%80%E5%8F%91%E4%B8%AD)%20AND%20RD%20in%20(currentUser())%20ORDER%20BY%20%E5%AE%9E%E9%99%85%E5%BC%80%E5%A7%8B%E5%BC%80%E5%8F%91%E6%97%B6%E9%97%B4%20ASC';
        $client = new Client([
            'base_uri' => $this->config['jira_base_uri']
        ]);

        $response = $client->request('GET', '/rest/api/2/search', [
            'auth' => [
                $this->config['oa_username'],
                $this->config['oa_password'],
            ],
            'query' => 'jql='.$jql,
        ]);
        //work has stopped, nothing to do, just test branch
        return (string)$response->getBody();
    }
}

