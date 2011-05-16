<?php

/**
 * TvShows 
 * 
 * @uses SilverBotPlugin
 * @final
 * @package 
 * @version $id$
 * @copyright 
 * @author Jared Mooring <jared.mooring@gmail.com> 
 */
final class TvShows extends SilverBotPlugin
{
	public function chn_tv($data)
	{
        $data = $data["data"];
        $data = explode(' ', $data);

        $max   = 10;
        if(empty($data)){ // !tv
            $shows = $this->_getShows();
            $this->_printShows($shows, $max);
        }
        elseif(is_numeric($data[0])) { // !tv 10
            $limit = $data[0];
            if($limit > 10)
                $limit = $max;
            $shows = $this->_getShows();
            $this->_printShows($shows, $limit);
        }
        else { // !tv entourage
            $grep = '';
            for($i = 0; $i < count($data); ++$i)
                $grep .= ' ' . $data[$i];
            $grep = trim($grep);
            $shows = $this->_getShows($grep);
            $this->_printShows($shows, count($shows));
        }
	}

	protected function _getShows($input = '')
	{
		$rss = (empty($input) ? $this->_getRSS() : $this->_getRSSEpisode() . urlencode($input));
		$data  = file_get_contents($rss);
		$shows = json_decode($data);
		$shows = $shows->value->items;

		return $shows;
	}

	protected function _printShows($shows, $max, $grep = '')
	{
		for($i = 0; $i < $max; $i++)
		{
			$show = $shows[$i];
			$str = sprintf('%s - %s', $show->title, $show->pubDate);
			$this->bot->reply($str);
		}
	}

    protected function _getRSS()
    {
        return 'http://pipes.yahoo.com/pipes/pipe.run?_id=e4cedb7b326c7d843f4779dcd86f4efb&_render=json';
    }

    protected function _getRSSEpisode()
    {
        return 'http://pipes.yahoo.com/pipes/pipe.run?_id=890ba68d660c1d840aeffcae580d426b&_render=json&textinput1=';
    }
}
