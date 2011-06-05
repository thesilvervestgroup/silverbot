<?php

/**
 * Love 
 * 
 * @uses SilverBotPlugin
 * @final
 * @package 
 * @version $id$
 * @copyright 
 * @author Jared Mooring <jared.mooring@gmail.com> 
 */
final class Quotes extends SilverBotPlugin
{
	public function __construct()
	{
		$this->_lovequotes   = split("\n", $this->_getLoveData());
		$this->_archerquotes = split("\n", $this->_getArcherData());
		$this->_hourcount = 0; // this is used to limit the number of love/hate per hour
		$this->_hour = date('H');
	}

    public function chn_love($data)
    {
        $this->_randQuote($data, $this->_lovequotes);
	}

    public function chn_archer($data)
    {
        $this->_randQuote($data, $this->_archerquotes);
	}

    protected function _randQuote($data, array $seed)
    {
        $data = $data['data'];
		if(empty($data))
			return ;

        if($this->_canAct()) {
            $name = explode(' ', $data);
            $name = $name[0];

            $n = array_rand($seed);
            $quote = $seed[$n];
            $this->bot->reply($quote . ' ' . $name); 
            $this->_hourcount++;
        }
    }

    protected function _canAct()
    {
        if(date('H') == $this->_hour) {
            $limited = false;
            if($this->_hourcount == $this->_getSayLimit()) {
                $this->bot->reply('I\'m sorry there is only so much quoting one bot can do in an hour');
                return false;
            }

            return true;
        }
        else {
            $this->_hour = date('H');
            $this->_hourcount = 0;
            return true;
        }
    }


    protected function _getLoveData()
    {
        return file_get_contents($this->getDataDirectory() . 'love');
    }

    protected function _getArcherData()
    {
        return file_get_contents($this->getDataDirectory(). 'archer');
    }

    protected function _getSayLimit()
    {
        return 8;
    }
}
?>
