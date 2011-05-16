<?php

/**
 * RapeFace 
 * 
 * @uses SilverBotPlugin
 * @final
 * @package 
 * @version $id$
 * @copyright 
 * @author Jared Mooring <jared.mooring@gmail.com> 
 */
final class Faces extends SilverBotPlugin
{

    public function chn_rapeface($data)
    {
        $data = $data["data"];
        if(empty($data))
            return;

        $name = explode(' ', $data);
        $name = strtolower($name[0]);

        if(isset($name)) {
            $faces = $this->_getFaces();
            if(isset($faces[$name])) {
                $rand = array_rand($faces[$name]);
                $url  = $this->_makeTinyUrl($faces[$name][$rand]);
                $this->bot->reply($name . ' has a pretty rapeface ' . $url);
                return;
            }
        }

        $rand = array_rand($faces['misc']);
        $this->bot->reply('RAPE RAPE! ' . $faces['misc'][$rand]);
    }

    public function prv_rehashfaces($data)
    {
        $this->_faces = array();
    }

    protected function _getFaces()
    {
        if(empty($this->_faces)) {
            $file = $this->getDataDirectory() . 'faces.php';
            if(!file_exists($file) && is_readable($file)) {
                $this->bot->reply("My user hasn't defined a faces file :(");
                return;
            }

            include $file;
            $this->_faces = $faces;
        }

        return $this->_faces;
    }

	private function _makeTinyUrl($url)
	{
		$url = 'http://tinyurl.com/api-create.php?url=' . $url;
		return file_get_contents($url);
	}
}
