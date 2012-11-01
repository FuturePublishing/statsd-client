<?php
/**
 * @copyright 2012 Future Publishing Ltd and Joseph Ray
 * @license   http://opensource.org/licenses/mit-license.php/ MIT
 */

namespace Future\Statsd;

/**
 * Sends statistics to the stats daemon over UDP
 *
 * Based on the etsy version at https://github.com/etsy/statsd/blob/master/examples/php-example.php
 */
class Client
{
    const TYPE_TIMING = 'ms';

    const TYPE_COUNT = 'c';

    const TYPE_GUAGE = 'g';

    const TYPE_SET = 's';

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
        $this->send(array($stat . ':' . $time . '|' . self::TYPE_TIMING), $sampleRate);
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
     * A "Set" is a count of unique events.
     * This data type acts like a counter, but supports counting
     * of unique occurences of values between flushes. The backend
     * receives the number of unique events that happened since
     * the last flush.
     *
     * The reference use case involved tracking the number of active
     * and logged in users by sending the current userId of a user
     * with each request with a key of "uniques" (or similar).
     *
     * @param string $stat  The set's name
     * @param float  $value The unique value to put in the set
     *
     * @access public
     *
     * @return void
     */
    public function set($stat, $value)
    {
        $this->send(array($stat . ':' . $value . '|' . self::TYPE_SET), 1);
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