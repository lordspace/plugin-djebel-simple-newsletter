<?php
/*
plugin_name: Djebel Simple Newsletter
plugin_uri: https://djebel.com/plugins/djebel-simple-newsletter
description: allows content creators to create social links to their social profiles.
version: 1.0.0
load_priority:20
tags: newseltter, email, marketing
stable_version: 1.0.0
min_php_ver: 5.6
min_dj_cms_ver: 1.0.0
tested_with_dj_cms_ver: 1.0.0
author_name: Svetoslav Marinov (Slavi)
company_name: Orbisius
author_uri: https://orbisius.com
text_domain: hello-world
license: gpl2
*/


$obj = new Djebel_Simple_Newsletter_Plugin();

class Djebel_Simple_Newsletter_Plugin
{
    private $file = '';

    public function __construct()
    {
        $file = Dj_App_Util::getDataDir() . '/plugins/djebel-simple-newsletter/{YYYY}/{MM}/data_{YYYY}-{MM}-{DD}.csv';

        $replace_str = [
            '{YYYY}' => date('Y'),
            '{MM}' => date('m'),
            '{DD}' => date('d'),
        ];

        $file = str_ireplace(array_keys($replace_str), array_values($replace_str), $file);
        $this->setFile($file);

        $shortcode_obj = Dj_App_Shortcode::getInstance();
        $shortcode_obj->addShortcode('djebel-simple-newsletter', [ $this, 'renderNewsletterForm', ] );

//        Dj_App_Hooks::addAction( 'app.page.body.start', [ $obj, 'renderNewsletterForm', ] );
    }

    /**
     * @todo send a code that once entered will confirm the subscription
     * @return void
     * @throws Exception
     */
    public function renderNewsletterForm($params = [])
    {
        $msg = '';
        $req_obj = Dj_App_Request::getInstance();
        $email = $req_obj->get('simple_newsletter_email');
        $email_enc = $req_obj->encode($email);

        if ($req_obj->isPost('simple_newsletter_email')) {
            try {
                if (empty($email)) {
                    throw new Dj_App_Exception('Please enter your email');
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Dj_App_Exception('Invalid email');
                }

                $data = [
                    'email' => $email,
                ];

                $ctx = [];
                $ctx['data'] = $data;
                Dj_App_Hooks::doAction( 'app.plugin.simple_newsletter.validate_data', $ctx );

                $data = [];
                $data['email'] = $email;
                $data['creation_date'] = date('r');
                $data['user_agent'] = $req_obj->getUserAgent();
                $data['ip'] = empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'];

                $data = Dj_App_Hooks::applyFilter( 'app.plugin.simple_newsletter.data', $data );

                // save this in csv using php csv
                $file = $this->getFile();

                $res = $this->writeCsv($file, $data);

                if ($res->isError()) {
                    throw new Dj_App_Exception('Failed to subscribe. Please try again later');
                }

                $email_enc = ''; // no need to show it again
                $msg = 'Done';
                $msg = Dj_App_Util::msg($msg, Dj_App_Util::MSG_SUCCESS);
            } catch (Exception $e) {
                $msg = $e->getMessage();
                $msg = Dj_App_Util::msg($msg);
            }
        }
        ?>
        <div class="djebel-simple-newsletter-msg"><?php echo $msg; ?></div>

        <form id="djebel-simple-newsletter-form" class="djebel-simple-newsletter-form" method="post" action="">
            <?php Dj_App_Hooks::doAction( 'app.plugin.simple_newsletter.form_start' ); ?>
            <input type="email" id="email" name="simple_newsletter_email" value="<?php echo $email_enc; ?>"
                   placeholder="Enter your email" required="required" />
            <button type="submit" name="simple_newsletter_submit">Subscribe</button>
            <?php Dj_App_Hooks::doAction( 'app.plugin.simple_newsletter.form_end' ); ?>
        </form>
        <?php
    }

    public function getFile(): string
    {
        $file = $this->file;
        $file = Dj_App_Hooks::applyFilter( 'app.plugin.simple_newsletter.file', $file );
        return $file;
    }

    public function setFile(string $file): void
    {
        $file = Dj_App_Hooks::applyFilter( 'app.plugin.simple_newsletter.set_file', $file );
        $this->file = $file;
    }

    /**
     * @param string $file
     * @param array $data
     * @return Dj_App_Result
     */
    public function writeCsv($file, $data = []) {
        $res_obj = new Dj_App_Result();
        $fp = null;

        try {
            Dj_App_Util::time( __METHOD__ );
            $dir = dirname($file);
            Dj_App_File_Util::mkdir($dir);

            $fp = fopen($file, 'ab');

            if (empty($fp)) {
                throw new Dj_App_File_Util_Exception("Couldn't create file", ['dir' => $dir]);
            }

            $fl_res = flock($fp, LOCK_EX);

            if (!$fl_res) {
                throw new Dj_App_File_Util_Exception("Couldn't lock file", ['file' => $file]);
            }

            // use csv
            $csv_res = fputcsv($fp, $data);

            if (empty($csv_res)) {
                throw new Dj_App_File_Util_Exception("Couldn't write to file", ['file' => $file]);
            }

            $res_obj->status(1);
        } catch (Exception $e) {
            $res_obj->msg = $e->getMessage();
        } finally {
            if (!empty($fp)) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }

            $res_obj->exec_time = Dj_App_Util::time( __METHOD__ );
        }

        return $res_obj;
    }
}