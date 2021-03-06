<?php
/*
 * VacationDriver base class
 *
 * @package plugins
 * @uses    rcube_plugin
 * @author  Jasper Slits <jaspersl at gmail dot com>
 * @version 1.9
 * @license GPL
 * @link    https://sourceforge.net/projects/rcubevacation/
 * @todo    See README.TXT
 */

abstract class VacationDriver
{
    protected $cfg;
    protected $dotforward = array();
    protected $rcmail;
    protected $user;
    protected $forward;
    protected $body;
    protected $subject;
    protected $aliases = '';
    protected $enable;
    protected $keepcopy = false;

    // Provide easy access for the drivers to frequently used objects
    public function __construct()
    {
        $this->rcmail = rcmail::get_instance();
        $this->user = $this->rcmail->user;
        $this->identity = $this->user->get_identity();
        $child_class = strtolower(get_class($this));
    }

    abstract public function _get();

    abstract public function init();

    final public function setIniConfig(array $inicfg)
    {
        $this->cfg = $inicfg;
    }

    final public function setDotForwardConfig($child_class, $config)
    {
        // forward settings are shared by ftp,sshftp and setuid driver.
        if (in_array($child_class, array('ftp', 'sshftp', 'setuid'))) {
            $this->dotforward = $config;
        }
    }

    // Helper method for the template to determine if user is allowed to enter aliases
    final public function useAliases()
    {
        return isset($this->dotforward['alias_identities']) && $this->dotforward['alias_identities'];
    }

    // Vacation auto reply is enabled? (jfcherng)
    final public function useVacationAutoReply()
    {
        return true;
    }

    public function loadDefaults()
    {
        // Load default subject and body.

        if (empty($this->cfg['body'])) {
            return false;
        }

        $file = 'plugins/vacation/'.$this->cfg['body'];

        if (is_readable($file)) {
            $defaults = array('subject' => $this->cfg['subject']);
            $defaults['body'] = file_get_contents($file);

            return $defaults;
        }
        raise_error(array('code' => 601, 'type' => 'php', 'file' => __FILE__,
            'message' => sprintf('Vacation plugin: s cannot be opened', $file),
        ), true, true);
    }

    // This method will be used from vacation.js as an JSON/Ajax call or directly
    final public function vacation_aliases($method = null)
    {
        $aliases = '';
        $identities = $this->user->list_identities();
        // Strip off the default identity, no need to alias that.
        array_shift($identities);

        foreach ($identities as $identity) {
            // Strip domainname off. /usr/bin/vacation only deals with system users

            $aliases .= array_shift(explode('@', $identity['email'])).',';
        }

        $str = substr($aliases, 0, -1);

        // We use this method in both ftp.class.php and as Ajax callback

        if (empty($identities)) {
            // No identities found.
            // $str = "To use aliases, add more identities.";
        }

        if ($method != null) {
            return $str;
        }
        // Calls the alias_callback as defined in vacation.js
        $this->rcmail->output->command('plugin.alias_callback', array('aliases' => $str));
    }

    // @return boolean True on succes, false on failure
    final public function save()
    {
        $this->enable = (null != rcube_utils::get_input_value('_vacation_enabled', rcube_utils::INPUT_POST));
        $this->subject = rcube_utils::get_input_value('_vacation_subject', rcube_utils::INPUT_POST);
        $this->body = rcube_utils::get_input_value('_vacation_body', rcube_utils::INPUT_POST);
        $this->keepcopy = (null != rcube_utils::get_input_value('_vacation_keepcopy', rcube_utils::INPUT_POST));
        $this->forward = rcube_utils::get_input_value('_vacation_forward', rcube_utils::INPUT_POST);
        $this->aliases = rcube_utils::get_input_value('_vacation_aliases', rcube_utils::INPUT_POST);

        // This method performs the actual work
        return $this->setVacation();
    }

    final public function getActionText()
    {
        if ($this->enable && empty($this->forward)) {
            return 'enabled_and_no_forward';
        }
        if ($this->enable && !empty($this->forward)) {
            return 'enabled_and_forward';
        }
        if (!$this->enable && !empty($this->forward)) {
            return 'disabled_and_forward';
        }
        if (!$this->enable && empty($this->forward)) {
            return 'disabled_and_no_forward';
        }
    }

    abstract protected function setVacation();
}
