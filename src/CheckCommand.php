<?php

namespace Acme;

use Google_Client;
use Google_Service_Sheets;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PHPMailer;

class CheckCommand extends Command
{

    private $config = [];
    
    
    public function __construct($config)
    {
        parent::__construct();        
        $this->config = $config;
    }

    /**
     * apply setter config
     */
    public function configure()
    {
        $this->setName("check")
                ->setDescription("Report luch by google sheet.")
                ->addArgument('day', InputArgument::OPTIONAL, 'Day nunmber to check. Defaul is to day', date('d'));
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        // Get the API client and construct the service object.
        $client = $this->getClient();
        $service = new Google_Service_Sheets($client);

        // Prints the names and majors of students in a sample spreadsheet:
        // https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit
        $spreadsheetId = $this->config['sheet_id'];
        
        $day = $input->getArgument('day');        
        $range = $day . '!B1:Q';
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues();

        if (count($values) == 0) {
            print "No data found.\n";
        } else {
            $courses = $this->processValues($values);
            $this->sendMail($courses, $day);
        }
    }
    
    /**
     * 
     * @param array $values
     * @param string $day day number
     * @return array
     */
    private function processValues($values)
    {        
        $courseRs = [];
        $courseCol = 0;
        $nameProviderCol = 1;
        $priceCol = 2;
        
        foreach ($values as $row) {
            if (trim($row[$nameProviderCol]) != 'Thành') {
                continue;
            }
            
            $courseName = $row[$courseCol];
            if (!isset($courseRs[$courseName])) {
                $courseRs[$courseName] = [];
            }

            for ($i = 3; $i < 15; $i ++) {
                if (trim($row[$i]) != '') {
                    $courseRs[$courseName][] = $row[$i];
                }
            }
        }
        
        //print_r($courseRs);
        //print_r($values);
        
        return $courseRs;
    }
    
    /**
     * 
     * @param type $courses
     * @param string $day day number
     */
    private function sendMail($courses, $day)
    {   
        $bodyStr = "";
        $tmpK = "";
        foreach($courses as $k => $list) {
            if ($tmpK != $k) {
                $tmpK = $k;
                $bodyStr .= "\n " . $k . "\n";
            }
            
            foreach ($list as $k1 =>  $item) {
                $no = $k1 + 1;
                $bodyStr .= "\t {$no}/ $item \n";
            }
        }
        
        $mail = new PHPMailer;        
        //$mail->SMTPDebug = 3;                               
        $mail->isSMTP();                                      
        $mail->Host = 'smtp.gmail.com';  
        $mail->SMTPAuth = true;                               
        $mail->Username = $this->config['email']['username']; 
        $mail->Password = $this->config['email']['password']; 
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        
        $mail->setFrom(
            $this->config['sendfrom']['email'], 
            $this->config['sendfrom']['name']
        );
        
        $mail->Subject = date('H:i') . " - " . $day . date('m') . ' Dat com';
        $mail->Body    = $bodyStr;
        echo $bodyStr;
        //Set who the message is to be sent to
        foreach ($this->config['sendto'] as $email => $name) {
            $mail->addAddress($email, $name);
        }

        //send the message, check for errors
        if (!$mail->send()) {
            echo "Mailer Error: " . $mail->ErrorInfo;
        } else {
            echo "Message sent!\n";
            //Section 2: IMAP
            //Uncomment these to save your message in the 'Sent Mail' folder.
            #if (save_mail($mail)) {
            #    echo "Message saved!";
            #}
        }
        
    }

    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    function getClient()
    {
        $client = new Google_Client();
        $client->setApplicationName(APPLICATION_NAME);
        $client->setScopes(SCOPES);
        $client->setAuthConfig(CLIENT_SECRET_PATH);
        $client->setAccessType('offline');
        $client->setRedirectUri('http://googlesheet.dev/quickstart.php');
        //$client->setRedirectUri($redirectUri);
        // Load previously authorized credentials from a file.
        //$credentialsPath = $this->expandHomeDirectory(CREDENTIALS_PATH);
        $credentialsPath = CREDENTIALS_PATH;
        if (file_exists($credentialsPath)) {
            $accessToken = json_decode(file_get_contents($credentialsPath), true);
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

            // Store the credentials to disk.
            if (!file_exists(dirname($credentialsPath))) {
                mkdir(dirname($credentialsPath), 0700, true);
            }
            file_put_contents($credentialsPath, json_encode($accessToken));
            printf("Credentials saved to %s\n", $credentialsPath);
        }
        $client->setAccessToken($accessToken);

        // Refresh the token if it's expired.
        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
        }
        return $client;
    }
    
    /**
     * Expands the home directory alias '~' to the full path.
     * @param string $path the path to expand.
     * @return string the expanded path.
     */
    function expandHomeDirectory($path)
    {
        $homeDirectory = getenv('HOME');
        if (empty($homeDirectory)) {
            $homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
        }
        return str_replace('~', realpath($homeDirectory), $path);
    }

}
