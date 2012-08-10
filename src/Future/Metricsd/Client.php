<?php

namespace Future\Metricsd;

/**
 * Sends statistics to the stats daemon over UDP
 *
 * Based on the etsy version at https://github.com/etsy/statsd/blob/master/examples/php-example.php
 *
 */
class Client
{
    const TYPE_TIMING = 'h';

    const TYPE_HISTOGRAM = 'h';

    const TYPE_COUNT = 'c';

    const TYPE_GUAGE = 'g';

    protected $connection;

    protected $enabled;

    protected $prefix;

    /**
     * Constructs the Client
     *
     * @param \Future\Network\Connection $connection The connection object to use to send stats
     * @param boolean                    $enabled    Whether to enable stats or not
     * @param string                     $prefix     A prefix to use when sending stats names
     *
     * @return void
     */
    public function __construct(\Future\Network\Connection $connection, $enabled, $prefix = '')
    {
        $this->connection = $connection;
        $this->enabled    = $enabled;
        $this->prefix     = $prefix;
    }

    /**
     * Log timing information
     *
     * @param string  $stat       The metric to in log timing info for.
     * @param int     $time       The ellapsed time (ms) to log
     * @param float|1 $sampleRate The rate (0-1) for sampling.
     *
     * @return void
     */
    public function timing($stat, $time, $sampleRate = 1)
    {
        $this->histogram($stat, $time, $sampleRate);
    }

    /**
     * Send a histogram metric
     *
     * @param string $stat       The stat identifier
     * @param int    $metric     The metric for the histogram
     * @param float  $samplerate The rate (0-1) for sampling
     *
     * @return void
     */
    public function histogram($stat, $metric, $samplerate = 1)
    {
        $this->send(array($stat . ':' . $metric . '|' . self::TYPE_HISTOGRAM), $samplerate);
    }

    /**
     * Adds a guage
     *
     * @param string $stat       The stat identifier
     * @param int    $value      The metric for the guage
     * @param float  $samplerate The rate (0-1) for sampling
     *
     * @return void
     */
    public function guage($stat, $value, $samplerate = 1)
    {
        $this->send(array($stat . ':' . $value . '|' . self::TYPE_GUAGE), $samplerate);
    }

    /**
     * Adds a meter
     *
     * @param string $stat       The identifier for the meter
     * @param float  $samplerate The rate (0-1) for sampling
     *
     * @return void
     */
    public function meter($stat, $samplerate = 1)
    {
        $this->send(array($stat), $samplerate);
    }

    /**
     * Increments one or more stats counters
     *
     * @param string|array $stats      The metric(s) to increment.
     * @param float|1      $sampleRate The rate (0-1) for sampling.
     *
     * @return void
     */
    public function increment($stats, $sampleRate = 1)
    {
        $this->updateStats($stats, 1, $sampleRate);
    }

    /**
     * Decrements one or more stats counters.
     *
     * @param string|array $stats      The metric(s) to decrement.
     * @param float|1      $sampleRate The rate (0-1) for sampling.
     *
     * @return void
     */
    public function decrement($stats, $sampleRate = 1)
    {
        $this->updateStats($stats, -1, $sampleRate);
    }

    /**
     * Updates one or more stats counters by arbitrary amounts.
     *
     * @param string|array $stats      The metric(s) to update. Should be either a string or array
     *                                 of metrics.
     * @param int|1        $delta      The amount to increment/decrement each metric by.
     * @param float|1      $sampleRate The rate (0-1) for sampling.
     *
     * @return void
     * */
    public function updateStats($stats, $delta = 1, $sampleRate = 1)
    {
        if (!is_array($stats)) {
            $stats = array($stats);
        }

        $data = array();

        foreach ($stats as $stat) {
            $data[] = $stat . ':' . $delta . '|' . self::TYPE_COUNT;
        }

        $this->send($data, $sampleRate);
    }

    /**
     * Deletes a metric
     *
     * @param string $stat The name of the stat
     * @param string $type The type of the stat (for convenience, use class consts Client::TYPE_*)
     *
     * @return void
     */
    public function delete($stat, $type)
    {
        $this->send(array($stat . ':' . 'delete|' . $type));
    }

    /**
     * Squirt the metrics over the network
     *
     * @param array $data       The array of strings to send
     * @param float $sampleRate The rate (0-1) for sampling
     *
     * @return void
     */
    protected function send($data, $sampleRate = 1)
    {
        if ($this->enabled === true) {
            // sampling
            $sampledData = array();

            if ($sampleRate < 1) {
                foreach ($data as $stat => $value) {
                    if ((mt_rand() / mt_getrandmax()) <= $sampleRate) {
                        $sampledData[$stat] = $value . '|@' . $sampleRate;
                    }
                }
            } else {
                $sampledData = $data;
            }

            if (!empty($sampledData)) {
                // Wrap this in a try/catch - failures in any of this should be silently ignored
                try {
                    foreach ($sampledData as $stat) {
                        $this->connection->send($this->prefix . $stat);
                    }
                } catch (Exception $e) {
                }
            }
        }
    }
}