<?php

namespace Zidisha\Payment\Paypal\Base;

use \DateTime;
use \Exception;
use \PDO;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\BadMethodCallException;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Parser\AbstractParser;
use Propel\Runtime\Util\PropelDateTime;
use Zidisha\Payment\Payment as ChildPayment;
use Zidisha\Payment\PaymentQuery;
use Zidisha\Payment\Paypal\PaypalTransaction as ChildPaypalTransaction;
use Zidisha\Payment\Paypal\PaypalTransactionQuery as ChildPaypalTransactionQuery;
use Zidisha\Payment\Paypal\Map\PaypalTransactionTableMap;

abstract class PaypalTransaction implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Zidisha\\Payment\\Paypal\\Map\\PaypalTransactionTableMap';


    /**
     * attribute to determine if this object has previously been saved.
     * @var boolean
     */
    protected $new = true;

    /**
     * attribute to determine whether this object has been deleted.
     * @var boolean
     */
    protected $deleted = false;

    /**
     * The columns that have been modified in current object.
     * Tracking modified columns allows us to only update modified columns.
     * @var array
     */
    protected $modifiedColumns = array();

    /**
     * The (virtual) columns that are added at runtime
     * The formatters can add supplementary columns based on a resultset
     * @var array
     */
    protected $virtualColumns = array();

    /**
     * The value for the id field.
     * @var        int
     */
    protected $id;

    /**
     * The value for the transaction_id field.
     * @var        string
     */
    protected $transaction_id;

    /**
     * The value for the transaction_type field.
     * @var        string
     */
    protected $transaction_type;

    /**
     * The value for the amount field.
     * @var        string
     */
    protected $amount;

    /**
     * The value for the donation_amount field.
     * @var        string
     */
    protected $donation_amount;

    /**
     * The value for the paypal_transaction_fee field.
     * @var        string
     */
    protected $paypal_transaction_fee;

    /**
     * The value for the total_amount field.
     * @var        string
     */
    protected $total_amount;

    /**
     * The value for the status field.
     * @var        string
     */
    protected $status;

    /**
     * The value for the custom field.
     * @var        string
     */
    protected $custom;

    /**
     * The value for the token field.
     * @var        string
     */
    protected $token;

    /**
     * The value for the payment_id field.
     * @var        int
     */
    protected $payment_id;

    /**
     * The value for the created_at field.
     * @var        \DateTime
     */
    protected $created_at;

    /**
     * The value for the updated_at field.
     * @var        \DateTime
     */
    protected $updated_at;

    /**
     * @var        ChildPayment
     */
    protected $aPayment;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

    /**
     * Initializes internal state of Zidisha\Payment\Paypal\Base\PaypalTransaction object.
     */
    public function __construct()
    {
    }

    /**
     * Returns whether the object has been modified.
     *
     * @return boolean True if the object has been modified.
     */
    public function isModified()
    {
        return !!$this->modifiedColumns;
    }

    /**
     * Has specified column been modified?
     *
     * @param  string  $col column fully qualified name (TableMap::TYPE_COLNAME), e.g. Book::AUTHOR_ID
     * @return boolean True if $col has been modified.
     */
    public function isColumnModified($col)
    {
        return $this->modifiedColumns && isset($this->modifiedColumns[$col]);
    }

    /**
     * Get the columns that have been modified in this object.
     * @return array A unique list of the modified column names for this object.
     */
    public function getModifiedColumns()
    {
        return $this->modifiedColumns ? array_keys($this->modifiedColumns) : [];
    }

    /**
     * Returns whether the object has ever been saved.  This will
     * be false, if the object was retrieved from storage or was created
     * and then saved.
     *
     * @return boolean true, if the object has never been persisted.
     */
    public function isNew()
    {
        return $this->new;
    }

    /**
     * Setter for the isNew attribute.  This method will be called
     * by Propel-generated children and objects.
     *
     * @param boolean $b the state of the object.
     */
    public function setNew($b)
    {
        $this->new = (boolean) $b;
    }

    /**
     * Whether this object has been deleted.
     * @return boolean The deleted state of this object.
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * Specify whether this object has been deleted.
     * @param  boolean $b The deleted state of this object.
     * @return void
     */
    public function setDeleted($b)
    {
        $this->deleted = (boolean) $b;
    }

    /**
     * Sets the modified state for the object to be false.
     * @param  string $col If supplied, only the specified column is reset.
     * @return void
     */
    public function resetModified($col = null)
    {
        if (null !== $col) {
            if (isset($this->modifiedColumns[$col])) {
                unset($this->modifiedColumns[$col]);
            }
        } else {
            $this->modifiedColumns = array();
        }
    }

    /**
     * Compares this with another <code>PaypalTransaction</code> instance.  If
     * <code>obj</code> is an instance of <code>PaypalTransaction</code>, delegates to
     * <code>equals(PaypalTransaction)</code>.  Otherwise, returns <code>false</code>.
     *
     * @param  mixed   $obj The object to compare to.
     * @return boolean Whether equal to the object specified.
     */
    public function equals($obj)
    {
        if (!$obj instanceof static) {
            return false;
        }

        if ($this === $obj) {
            return true;
        }

        if (null === $this->getPrimaryKey() || null === $obj->getPrimaryKey()) {
            return false;
        }

        return $this->getPrimaryKey() === $obj->getPrimaryKey();
    }

    /**
     * Get the associative array of the virtual columns in this object
     *
     * @return array
     */
    public function getVirtualColumns()
    {
        return $this->virtualColumns;
    }

    /**
     * Checks the existence of a virtual column in this object
     *
     * @param  string  $name The virtual column name
     * @return boolean
     */
    public function hasVirtualColumn($name)
    {
        return array_key_exists($name, $this->virtualColumns);
    }

    /**
     * Get the value of a virtual column in this object
     *
     * @param  string $name The virtual column name
     * @return mixed
     *
     * @throws PropelException
     */
    public function getVirtualColumn($name)
    {
        if (!$this->hasVirtualColumn($name)) {
            throw new PropelException(sprintf('Cannot get value of inexistent virtual column %s.', $name));
        }

        return $this->virtualColumns[$name];
    }

    /**
     * Set the value of a virtual column in this object
     *
     * @param string $name  The virtual column name
     * @param mixed  $value The value to give to the virtual column
     *
     * @return $this|PaypalTransaction The current object, for fluid interface
     */
    public function setVirtualColumn($name, $value)
    {
        $this->virtualColumns[$name] = $value;

        return $this;
    }

    /**
     * Logs a message using Propel::log().
     *
     * @param  string  $msg
     * @param  int     $priority One of the Propel::LOG_* logging levels
     * @return boolean
     */
    protected function log($msg, $priority = Propel::LOG_INFO)
    {
        return Propel::log(get_class($this) . ': ' . $msg, $priority);
    }

    /**
     * Export the current object properties to a string, using a given parser format
     * <code>
     * $book = BookQuery::create()->findPk(9012);
     * echo $book->exportTo('JSON');
     *  => {"Id":9012,"Title":"Don Juan","ISBN":"0140422161","Price":12.99,"PublisherId":1234,"AuthorId":5678}');
     * </code>
     *
     * @param  mixed   $parser                 A AbstractParser instance, or a format name ('XML', 'YAML', 'JSON', 'CSV')
     * @param  boolean $includeLazyLoadColumns (optional) Whether to include lazy load(ed) columns. Defaults to TRUE.
     * @return string  The exported data
     */
    public function exportTo($parser, $includeLazyLoadColumns = true)
    {
        if (!$parser instanceof AbstractParser) {
            $parser = AbstractParser::getParser($parser);
        }

        return $parser->fromArray($this->toArray(TableMap::TYPE_PHPNAME, $includeLazyLoadColumns, array(), true));
    }

    /**
     * Clean up internal collections prior to serializing
     * Avoids recursive loops that turn into segmentation faults when serializing
     */
    public function __sleep()
    {
        $this->clearAllReferences();

        return array_keys(get_object_vars($this));
    }

    /**
     * Get the [id] column value.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the [transaction_id] column value.
     *
     * @return string
     */
    public function getTransactionId()
    {
        return $this->transaction_id;
    }

    /**
     * Get the [transaction_type] column value.
     *
     * @return string
     */
    public function getTransactionType()
    {
        return $this->transaction_type;
    }

    /**
     * Get the [amount] column value.
     *
     * @return string
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Get the [donation_amount] column value.
     *
     * @return string
     */
    public function getDonationAmount()
    {
        return $this->donation_amount;
    }

    /**
     * Get the [paypal_transaction_fee] column value.
     *
     * @return string
     */
    public function getPaypalTransactionFee()
    {
        return $this->paypal_transaction_fee;
    }

    /**
     * Get the [total_amount] column value.
     *
     * @return string
     */
    public function getTotalAmount()
    {
        return $this->total_amount;
    }

    /**
     * Get the [status] column value.
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Get the [custom] column value.
     *
     * @return string
     */
    public function getCustom()
    {
        return $this->custom;
    }

    /**
     * Get the [token] column value.
     *
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Get the [payment_id] column value.
     *
     * @return int
     */
    public function getPaymentId()
    {
        return $this->payment_id;
    }

    /**
     * Get the [optionally formatted] temporal [created_at] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return string|\DateTime Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getCreatedAt($format = NULL)
    {
        if ($format === null) {
            return $this->created_at;
        } else {
            return $this->created_at instanceof \DateTime ? $this->created_at->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [updated_at] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return string|\DateTime Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getUpdatedAt($format = NULL)
    {
        if ($format === null) {
            return $this->updated_at;
        } else {
            return $this->updated_at instanceof \DateTime ? $this->updated_at->format($format) : null;
        }
    }

    /**
     * Indicates whether the columns in this object are only set to default values.
     *
     * This method can be used in conjunction with isModified() to indicate whether an object is both
     * modified _and_ has some values set which are non-default.
     *
     * @return boolean Whether the columns in this object are only been set with default values.
     */
    public function hasOnlyDefaultValues()
    {
        // otherwise, everything was equal, so return TRUE
        return true;
    } // hasOnlyDefaultValues()

    /**
     * Hydrates (populates) the object variables with values from the database resultset.
     *
     * An offset (0-based "start column") is specified so that objects can be hydrated
     * with a subset of the columns in the resultset rows.  This is needed, for example,
     * for results of JOIN queries where the resultset row includes columns from two or
     * more tables.
     *
     * @param array   $row       The row returned by DataFetcher->fetch().
     * @param int     $startcol  0-based offset column which indicates which restultset column to start with.
     * @param boolean $rehydrate Whether this object is being re-hydrated from the database.
     * @param string  $indexType The index type of $row. Mostly DataFetcher->getIndexType().
                                  One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                            TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *
     * @return int             next starting column
     * @throws PropelException - Any caught Exception will be rewrapped as a PropelException.
     */
    public function hydrate($row, $startcol = 0, $rehydrate = false, $indexType = TableMap::TYPE_NUM)
    {
        try {

            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : PaypalTransactionTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : PaypalTransactionTableMap::translateFieldName('TransactionId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->transaction_id = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : PaypalTransactionTableMap::translateFieldName('TransactionType', TableMap::TYPE_PHPNAME, $indexType)];
            $this->transaction_type = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : PaypalTransactionTableMap::translateFieldName('Amount', TableMap::TYPE_PHPNAME, $indexType)];
            $this->amount = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : PaypalTransactionTableMap::translateFieldName('DonationAmount', TableMap::TYPE_PHPNAME, $indexType)];
            $this->donation_amount = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : PaypalTransactionTableMap::translateFieldName('PaypalTransactionFee', TableMap::TYPE_PHPNAME, $indexType)];
            $this->paypal_transaction_fee = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : PaypalTransactionTableMap::translateFieldName('TotalAmount', TableMap::TYPE_PHPNAME, $indexType)];
            $this->total_amount = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : PaypalTransactionTableMap::translateFieldName('Status', TableMap::TYPE_PHPNAME, $indexType)];
            $this->status = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : PaypalTransactionTableMap::translateFieldName('Custom', TableMap::TYPE_PHPNAME, $indexType)];
            $this->custom = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : PaypalTransactionTableMap::translateFieldName('Token', TableMap::TYPE_PHPNAME, $indexType)];
            $this->token = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : PaypalTransactionTableMap::translateFieldName('PaymentId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->payment_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : PaypalTransactionTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : PaypalTransactionTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 13; // 13 = PaypalTransactionTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException(sprintf('Error populating %s object', '\\Zidisha\\Payment\\Paypal\\PaypalTransaction'), 0, $e);
        }
    }

    /**
     * Checks and repairs the internal consistency of the object.
     *
     * This method is executed after an already-instantiated object is re-hydrated
     * from the database.  It exists to check any foreign keys to make sure that
     * the objects related to the current object are correct based on foreign key.
     *
     * You can override this method in the stub class, but you should always invoke
     * the base method from the overridden method (i.e. parent::ensureConsistency()),
     * in case your model changes.
     *
     * @throws PropelException
     */
    public function ensureConsistency()
    {
        if ($this->aPayment !== null && $this->payment_id !== $this->aPayment->getId()) {
            $this->aPayment = null;
        }
    } // ensureConsistency

    /**
     * Set the value of [id] column.
     *
     * @param  int $v new value
     * @return $this|\Zidisha\Payment\Paypal\PaypalTransaction The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[PaypalTransactionTableMap::COL_ID] = true;
        }

        return $this;
    } // setId()

    /**
     * Set the value of [transaction_id] column.
     *
     * @param  string $v new value
     * @return $this|\Zidisha\Payment\Paypal\PaypalTransaction The current object (for fluent API support)
     */
    public function setTransactionId($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->transaction_id !== $v) {
            $this->transaction_id = $v;
            $this->modifiedColumns[PaypalTransactionTableMap::COL_TRANSACTION_ID] = true;
        }

        return $this;
    } // setTransactionId()

    /**
     * Set the value of [transaction_type] column.
     *
     * @param  string $v new value
     * @return $this|\Zidisha\Payment\Paypal\PaypalTransaction The current object (for fluent API support)
     */
    public function setTransactionType($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->transaction_type !== $v) {
            $this->transaction_type = $v;
            $this->modifiedColumns[PaypalTransactionTableMap::COL_TRANSACTION_TYPE] = true;
        }

        return $this;
    } // setTransactionType()

    /**
     * Set the value of [amount] column.
     *
     * @param  string $v new value
     * @return $this|\Zidisha\Payment\Paypal\PaypalTransaction The current object (for fluent API support)
     */
    public function setAmount($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->amount !== $v) {
            $this->amount = $v;
            $this->modifiedColumns[PaypalTransactionTableMap::COL_AMOUNT] = true;
        }

        return $this;
    } // setAmount()

    /**
     * Set the value of [donation_amount] column.
     *
     * @param  string $v new value
     * @return $this|\Zidisha\Payment\Paypal\PaypalTransaction The current object (for fluent API support)
     */
    public function setDonationAmount($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->donation_amount !== $v) {
            $this->donation_amount = $v;
            $this->modifiedColumns[PaypalTransactionTableMap::COL_DONATION_AMOUNT] = true;
        }

        return $this;
    } // setDonationAmount()

    /**
     * Set the value of [paypal_transaction_fee] column.
     *
     * @param  string $v new value
     * @return $this|\Zidisha\Payment\Paypal\PaypalTransaction The current object (for fluent API support)
     */
    public function setPaypalTransactionFee($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->paypal_transaction_fee !== $v) {
            $this->paypal_transaction_fee = $v;
            $this->modifiedColumns[PaypalTransactionTableMap::COL_PAYPAL_TRANSACTION_FEE] = true;
        }

        return $this;
    } // setPaypalTransactionFee()

    /**
     * Set the value of [total_amount] column.
     *
     * @param  string $v new value
     * @return $this|\Zidisha\Payment\Paypal\PaypalTransaction The current object (for fluent API support)
     */
    public function setTotalAmount($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->total_amount !== $v) {
            $this->total_amount = $v;
            $this->modifiedColumns[PaypalTransactionTableMap::COL_TOTAL_AMOUNT] = true;
        }

        return $this;
    } // setTotalAmount()

    /**
     * Set the value of [status] column.
     *
     * @param  string $v new value
     * @return $this|\Zidisha\Payment\Paypal\PaypalTransaction The current object (for fluent API support)
     */
    public function setStatus($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->status !== $v) {
            $this->status = $v;
            $this->modifiedColumns[PaypalTransactionTableMap::COL_STATUS] = true;
        }

        return $this;
    } // setStatus()

    /**
     * Set the value of [custom] column.
     *
     * @param  string $v new value
     * @return $this|\Zidisha\Payment\Paypal\PaypalTransaction The current object (for fluent API support)
     */
    public function setCustom($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->custom !== $v) {
            $this->custom = $v;
            $this->modifiedColumns[PaypalTransactionTableMap::COL_CUSTOM] = true;
        }

        return $this;
    } // setCustom()

    /**
     * Set the value of [token] column.
     *
     * @param  string $v new value
     * @return $this|\Zidisha\Payment\Paypal\PaypalTransaction The current object (for fluent API support)
     */
    public function setToken($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->token !== $v) {
            $this->token = $v;
            $this->modifiedColumns[PaypalTransactionTableMap::COL_TOKEN] = true;
        }

        return $this;
    } // setToken()

    /**
     * Set the value of [payment_id] column.
     *
     * @param  int $v new value
     * @return $this|\Zidisha\Payment\Paypal\PaypalTransaction The current object (for fluent API support)
     */
    public function setPaymentId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->payment_id !== $v) {
            $this->payment_id = $v;
            $this->modifiedColumns[PaypalTransactionTableMap::COL_PAYMENT_ID] = true;
        }

        if ($this->aPayment !== null && $this->aPayment->getId() !== $v) {
            $this->aPayment = null;
        }

        return $this;
    } // setPaymentId()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return $this|\Zidisha\Payment\Paypal\PaypalTransaction The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[PaypalTransactionTableMap::COL_CREATED_AT] = true;
            }
        } // if either are not null

        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return $this|\Zidisha\Payment\Paypal\PaypalTransaction The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[PaypalTransactionTableMap::COL_UPDATED_AT] = true;
            }
        } // if either are not null

        return $this;
    } // setUpdatedAt()

    /**
     * Reloads this object from datastore based on primary key and (optionally) resets all associated objects.
     *
     * This will only work if the object has been saved and has a valid primary key set.
     *
     * @param      boolean $deep (optional) Whether to also de-associated any related objects.
     * @param      ConnectionInterface $con (optional) The ConnectionInterface connection to use.
     * @return void
     * @throws PropelException - if this object is deleted, unsaved or doesn't have pk match in db
     */
    public function reload($deep = false, ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("Cannot reload a deleted object.");
        }

        if ($this->isNew()) {
            throw new PropelException("Cannot reload an unsaved object.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(PaypalTransactionTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildPaypalTransactionQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aPayment = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see PaypalTransaction::setDeleted()
     * @see PaypalTransaction::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(PaypalTransactionTableMap::DATABASE_NAME);
        }

        $con->transaction(function () use ($con) {
            $deleteQuery = ChildPaypalTransactionQuery::create()
                ->filterByPrimaryKey($this->getPrimaryKey());
            $ret = $this->preDelete($con);
            if ($ret) {
                $deleteQuery->delete($con);
                $this->postDelete($con);
                $this->setDeleted(true);
            }
        });
    }

    /**
     * Persists this object to the database.
     *
     * If the object is new, it inserts it; otherwise an update is performed.
     * All modified related objects will also be persisted in the doSave()
     * method.  This method wraps all precipitate database operations in a
     * single transaction.
     *
     * @param      ConnectionInterface $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @see doSave()
     */
    public function save(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("You cannot save an object that has been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(PaypalTransactionTableMap::DATABASE_NAME);
        }

        return $con->transaction(function () use ($con) {
            $isInsert = $this->isNew();
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior

                if (!$this->isColumnModified(PaypalTransactionTableMap::COL_CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(PaypalTransactionTableMap::COL_UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(PaypalTransactionTableMap::COL_UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            }
            if ($ret) {
                $affectedRows = $this->doSave($con);
                if ($isInsert) {
                    $this->postInsert($con);
                } else {
                    $this->postUpdate($con);
                }
                $this->postSave($con);
                PaypalTransactionTableMap::addInstanceToPool($this);
            } else {
                $affectedRows = 0;
            }

            return $affectedRows;
        });
    }

    /**
     * Performs the work of inserting or updating the row in the database.
     *
     * If the object is new, it inserts it; otherwise an update is performed.
     * All related objects are also updated in this method.
     *
     * @param      ConnectionInterface $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @see save()
     */
    protected function doSave(ConnectionInterface $con)
    {
        $affectedRows = 0; // initialize var to track total num of affected rows
        if (!$this->alreadyInSave) {
            $this->alreadyInSave = true;

            // We call the save method on the following object(s) if they
            // were passed to this object by their corresponding set
            // method.  This object relates to these object(s) by a
            // foreign key reference.

            if ($this->aPayment !== null) {
                if ($this->aPayment->isModified() || $this->aPayment->isNew()) {
                    $affectedRows += $this->aPayment->save($con);
                }
                $this->setPayment($this->aPayment);
            }

            if ($this->isNew() || $this->isModified()) {
                // persist changes
                if ($this->isNew()) {
                    $this->doInsert($con);
                } else {
                    $this->doUpdate($con);
                }
                $affectedRows += 1;
                $this->resetModified();
            }

            $this->alreadyInSave = false;

        }

        return $affectedRows;
    } // doSave()

    /**
     * Insert the row in the database.
     *
     * @param      ConnectionInterface $con
     *
     * @throws PropelException
     * @see doSave()
     */
    protected function doInsert(ConnectionInterface $con)
    {
        $modifiedColumns = array();
        $index = 0;

        $this->modifiedColumns[PaypalTransactionTableMap::COL_ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . PaypalTransactionTableMap::COL_ID . ')');
        }
        if (null === $this->id) {
            try {
                $dataFetcher = $con->query("SELECT nextval('paypal_transactions_id_seq')");
                $this->id = $dataFetcher->fetchColumn();
            } catch (Exception $e) {
                throw new PropelException('Unable to get sequence id.', 0, $e);
            }
        }


         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_ID)) {
            $modifiedColumns[':p' . $index++]  = 'ID';
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_TRANSACTION_ID)) {
            $modifiedColumns[':p' . $index++]  = 'TRANSACTION_ID';
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_TRANSACTION_TYPE)) {
            $modifiedColumns[':p' . $index++]  = 'TRANSACTION_TYPE';
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_AMOUNT)) {
            $modifiedColumns[':p' . $index++]  = 'AMOUNT';
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_DONATION_AMOUNT)) {
            $modifiedColumns[':p' . $index++]  = 'DONATION_AMOUNT';
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_PAYPAL_TRANSACTION_FEE)) {
            $modifiedColumns[':p' . $index++]  = 'PAYPAL_TRANSACTION_FEE';
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_TOTAL_AMOUNT)) {
            $modifiedColumns[':p' . $index++]  = 'TOTAL_AMOUNT';
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_STATUS)) {
            $modifiedColumns[':p' . $index++]  = 'STATUS';
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_CUSTOM)) {
            $modifiedColumns[':p' . $index++]  = 'CUSTOM';
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_TOKEN)) {
            $modifiedColumns[':p' . $index++]  = 'TOKEN';
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_PAYMENT_ID)) {
            $modifiedColumns[':p' . $index++]  = 'PAYMENT_ID';
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = 'CREATED_AT';
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = 'UPDATED_AT';
        }

        $sql = sprintf(
            'INSERT INTO paypal_transactions (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'ID':
                        $stmt->bindValue($identifier, $this->id, PDO::PARAM_INT);
                        break;
                    case 'TRANSACTION_ID':
                        $stmt->bindValue($identifier, $this->transaction_id, PDO::PARAM_STR);
                        break;
                    case 'TRANSACTION_TYPE':
                        $stmt->bindValue($identifier, $this->transaction_type, PDO::PARAM_STR);
                        break;
                    case 'AMOUNT':
                        $stmt->bindValue($identifier, $this->amount, PDO::PARAM_STR);
                        break;
                    case 'DONATION_AMOUNT':
                        $stmt->bindValue($identifier, $this->donation_amount, PDO::PARAM_STR);
                        break;
                    case 'PAYPAL_TRANSACTION_FEE':
                        $stmt->bindValue($identifier, $this->paypal_transaction_fee, PDO::PARAM_STR);
                        break;
                    case 'TOTAL_AMOUNT':
                        $stmt->bindValue($identifier, $this->total_amount, PDO::PARAM_STR);
                        break;
                    case 'STATUS':
                        $stmt->bindValue($identifier, $this->status, PDO::PARAM_STR);
                        break;
                    case 'CUSTOM':
                        $stmt->bindValue($identifier, $this->custom, PDO::PARAM_STR);
                        break;
                    case 'TOKEN':
                        $stmt->bindValue($identifier, $this->token, PDO::PARAM_STR);
                        break;
                    case 'PAYMENT_ID':
                        $stmt->bindValue($identifier, $this->payment_id, PDO::PARAM_INT);
                        break;
                    case 'CREATED_AT':
                        $stmt->bindValue($identifier, $this->created_at ? $this->created_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'UPDATED_AT':
                        $stmt->bindValue($identifier, $this->updated_at ? $this->updated_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                }
            }
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute INSERT statement [%s]', $sql), 0, $e);
        }

        $this->setNew(false);
    }

    /**
     * Update the row in the database.
     *
     * @param      ConnectionInterface $con
     *
     * @return Integer Number of updated rows
     * @see doSave()
     */
    protected function doUpdate(ConnectionInterface $con)
    {
        $selectCriteria = $this->buildPkeyCriteria();
        $valuesCriteria = $this->buildCriteria();

        return $selectCriteria->doUpdate($valuesCriteria, $con);
    }

    /**
     * Retrieves a field from the object by name passed in as a string.
     *
     * @param      string $name name
     * @param      string $type The type of fieldname the $name is of:
     *                     one of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                     TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                     Defaults to TableMap::TYPE_PHPNAME.
     * @return mixed Value of field.
     */
    public function getByName($name, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = PaypalTransactionTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
        $field = $this->getByPosition($pos);

        return $field;
    }

    /**
     * Retrieves a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param      int $pos position in xml schema
     * @return mixed Value of field at $pos
     */
    public function getByPosition($pos)
    {
        switch ($pos) {
            case 0:
                return $this->getId();
                break;
            case 1:
                return $this->getTransactionId();
                break;
            case 2:
                return $this->getTransactionType();
                break;
            case 3:
                return $this->getAmount();
                break;
            case 4:
                return $this->getDonationAmount();
                break;
            case 5:
                return $this->getPaypalTransactionFee();
                break;
            case 6:
                return $this->getTotalAmount();
                break;
            case 7:
                return $this->getStatus();
                break;
            case 8:
                return $this->getCustom();
                break;
            case 9:
                return $this->getToken();
                break;
            case 10:
                return $this->getPaymentId();
                break;
            case 11:
                return $this->getCreatedAt();
                break;
            case 12:
                return $this->getUpdatedAt();
                break;
            default:
                return null;
                break;
        } // switch()
    }

    /**
     * Exports the object as an array.
     *
     * You can specify the key type of the array by passing one of the class
     * type constants.
     *
     * @param     string  $keyType (optional) One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME,
     *                    TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                    Defaults to TableMap::TYPE_PHPNAME.
     * @param     boolean $includeLazyLoadColumns (optional) Whether to include lazy loaded columns. Defaults to TRUE.
     * @param     array $alreadyDumpedObjects List of objects to skip to avoid recursion
     * @param     boolean $includeForeignObjects (optional) Whether to include hydrated related objects. Default to FALSE.
     *
     * @return array an associative array containing the field names (as keys) and field values
     */
    public function toArray($keyType = TableMap::TYPE_PHPNAME, $includeLazyLoadColumns = true, $alreadyDumpedObjects = array(), $includeForeignObjects = false)
    {
        if (isset($alreadyDumpedObjects['PaypalTransaction'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['PaypalTransaction'][$this->getPrimaryKey()] = true;
        $keys = PaypalTransactionTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getTransactionId(),
            $keys[2] => $this->getTransactionType(),
            $keys[3] => $this->getAmount(),
            $keys[4] => $this->getDonationAmount(),
            $keys[5] => $this->getPaypalTransactionFee(),
            $keys[6] => $this->getTotalAmount(),
            $keys[7] => $this->getStatus(),
            $keys[8] => $this->getCustom(),
            $keys[9] => $this->getToken(),
            $keys[10] => $this->getPaymentId(),
            $keys[11] => $this->getCreatedAt(),
            $keys[12] => $this->getUpdatedAt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->aPayment) {
                $result['Payment'] = $this->aPayment->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
        }

        return $result;
    }

    /**
     * Sets a field from the object by name passed in as a string.
     *
     * @param  string $name
     * @param  mixed  $value field value
     * @param  string $type The type of fieldname the $name is of:
     *                one of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                Defaults to TableMap::TYPE_PHPNAME.
     * @return $this|\Zidisha\Payment\Paypal\PaypalTransaction
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = PaypalTransactionTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param  int $pos position in xml schema
     * @param  mixed $value field value
     * @return $this|\Zidisha\Payment\Paypal\PaypalTransaction
     */
    public function setByPosition($pos, $value)
    {
        switch ($pos) {
            case 0:
                $this->setId($value);
                break;
            case 1:
                $this->setTransactionId($value);
                break;
            case 2:
                $this->setTransactionType($value);
                break;
            case 3:
                $this->setAmount($value);
                break;
            case 4:
                $this->setDonationAmount($value);
                break;
            case 5:
                $this->setPaypalTransactionFee($value);
                break;
            case 6:
                $this->setTotalAmount($value);
                break;
            case 7:
                $this->setStatus($value);
                break;
            case 8:
                $this->setCustom($value);
                break;
            case 9:
                $this->setToken($value);
                break;
            case 10:
                $this->setPaymentId($value);
                break;
            case 11:
                $this->setCreatedAt($value);
                break;
            case 12:
                $this->setUpdatedAt($value);
                break;
        } // switch()

        return $this;
    }

    /**
     * Populates the object using an array.
     *
     * This is particularly useful when populating an object from one of the
     * request arrays (e.g. $_POST).  This method goes through the column
     * names, checking to see whether a matching key exists in populated
     * array. If so the setByName() method is called for that column.
     *
     * You can specify the key type of the array by additionally passing one
     * of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME,
     * TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     * The default key type is the column's TableMap::TYPE_PHPNAME.
     *
     * @param      array  $arr     An array to populate the object from.
     * @param      string $keyType The type of keys the array uses.
     * @return void
     */
    public function fromArray($arr, $keyType = TableMap::TYPE_PHPNAME)
    {
        $keys = PaypalTransactionTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) {
            $this->setId($arr[$keys[0]]);
        }
        if (array_key_exists($keys[1], $arr)) {
            $this->setTransactionId($arr[$keys[1]]);
        }
        if (array_key_exists($keys[2], $arr)) {
            $this->setTransactionType($arr[$keys[2]]);
        }
        if (array_key_exists($keys[3], $arr)) {
            $this->setAmount($arr[$keys[3]]);
        }
        if (array_key_exists($keys[4], $arr)) {
            $this->setDonationAmount($arr[$keys[4]]);
        }
        if (array_key_exists($keys[5], $arr)) {
            $this->setPaypalTransactionFee($arr[$keys[5]]);
        }
        if (array_key_exists($keys[6], $arr)) {
            $this->setTotalAmount($arr[$keys[6]]);
        }
        if (array_key_exists($keys[7], $arr)) {
            $this->setStatus($arr[$keys[7]]);
        }
        if (array_key_exists($keys[8], $arr)) {
            $this->setCustom($arr[$keys[8]]);
        }
        if (array_key_exists($keys[9], $arr)) {
            $this->setToken($arr[$keys[9]]);
        }
        if (array_key_exists($keys[10], $arr)) {
            $this->setPaymentId($arr[$keys[10]]);
        }
        if (array_key_exists($keys[11], $arr)) {
            $this->setCreatedAt($arr[$keys[11]]);
        }
        if (array_key_exists($keys[12], $arr)) {
            $this->setUpdatedAt($arr[$keys[12]]);
        }
    }

     /**
     * Populate the current object from a string, using a given parser format
     * <code>
     * $book = new Book();
     * $book->importFrom('JSON', '{"Id":9012,"Title":"Don Juan","ISBN":"0140422161","Price":12.99,"PublisherId":1234,"AuthorId":5678}');
     * </code>
     *
     * @param mixed $parser A AbstractParser instance,
     *                       or a format name ('XML', 'YAML', 'JSON', 'CSV')
     * @param string $data The source data to import from
     *
     * @return $this|\Zidisha\Payment\Paypal\PaypalTransaction The current object, for fluid interface
     */
    public function importFrom($parser, $data)
    {
        if (!$parser instanceof AbstractParser) {
            $parser = AbstractParser::getParser($parser);
        }

        $this->fromArray($parser->toArray($data), TableMap::TYPE_PHPNAME);

        return $this;
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(PaypalTransactionTableMap::DATABASE_NAME);

        if ($this->isColumnModified(PaypalTransactionTableMap::COL_ID)) {
            $criteria->add(PaypalTransactionTableMap::COL_ID, $this->id);
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_TRANSACTION_ID)) {
            $criteria->add(PaypalTransactionTableMap::COL_TRANSACTION_ID, $this->transaction_id);
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_TRANSACTION_TYPE)) {
            $criteria->add(PaypalTransactionTableMap::COL_TRANSACTION_TYPE, $this->transaction_type);
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_AMOUNT)) {
            $criteria->add(PaypalTransactionTableMap::COL_AMOUNT, $this->amount);
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_DONATION_AMOUNT)) {
            $criteria->add(PaypalTransactionTableMap::COL_DONATION_AMOUNT, $this->donation_amount);
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_PAYPAL_TRANSACTION_FEE)) {
            $criteria->add(PaypalTransactionTableMap::COL_PAYPAL_TRANSACTION_FEE, $this->paypal_transaction_fee);
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_TOTAL_AMOUNT)) {
            $criteria->add(PaypalTransactionTableMap::COL_TOTAL_AMOUNT, $this->total_amount);
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_STATUS)) {
            $criteria->add(PaypalTransactionTableMap::COL_STATUS, $this->status);
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_CUSTOM)) {
            $criteria->add(PaypalTransactionTableMap::COL_CUSTOM, $this->custom);
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_TOKEN)) {
            $criteria->add(PaypalTransactionTableMap::COL_TOKEN, $this->token);
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_PAYMENT_ID)) {
            $criteria->add(PaypalTransactionTableMap::COL_PAYMENT_ID, $this->payment_id);
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_CREATED_AT)) {
            $criteria->add(PaypalTransactionTableMap::COL_CREATED_AT, $this->created_at);
        }
        if ($this->isColumnModified(PaypalTransactionTableMap::COL_UPDATED_AT)) {
            $criteria->add(PaypalTransactionTableMap::COL_UPDATED_AT, $this->updated_at);
        }

        return $criteria;
    }

    /**
     * Builds a Criteria object containing the primary key for this object.
     *
     * Unlike buildCriteria() this method includes the primary key values regardless
     * of whether or not they have been modified.
     *
     * @throws LogicException if no primary key is defined
     *
     * @return Criteria The Criteria object containing value(s) for primary key(s).
     */
    public function buildPkeyCriteria()
    {
        $criteria = new Criteria(PaypalTransactionTableMap::DATABASE_NAME);
        $criteria->add(PaypalTransactionTableMap::COL_ID, $this->id);

        return $criteria;
    }

    /**
     * If the primary key is not null, return the hashcode of the
     * primary key. Otherwise, return the hash code of the object.
     *
     * @return int Hashcode
     */
    public function hashCode()
    {
        $validPk = null !== $this->getId();

        $validPrimaryKeyFKs = 0;
        $primaryKeyFKs = [];

        if ($validPk) {
            return crc32(json_encode($this->getPrimaryKey(), JSON_UNESCAPED_UNICODE));
        } elseif ($validPrimaryKeyFKs) {
            return crc32(json_encode($primaryKeyFKs, JSON_UNESCAPED_UNICODE));
        }

        return spl_object_hash($this);
    }

    /**
     * Returns the primary key for this object (row).
     * @return int
     */
    public function getPrimaryKey()
    {
        return $this->getId();
    }

    /**
     * Generic method to set the primary key (id column).
     *
     * @param       int $key Primary key.
     * @return void
     */
    public function setPrimaryKey($key)
    {
        $this->setId($key);
    }

    /**
     * Returns true if the primary key for this object is null.
     * @return boolean
     */
    public function isPrimaryKeyNull()
    {
        return null === $this->getId();
    }

    /**
     * Sets contents of passed object to values from current object.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      object $copyObj An object of \Zidisha\Payment\Paypal\PaypalTransaction (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setTransactionId($this->getTransactionId());
        $copyObj->setTransactionType($this->getTransactionType());
        $copyObj->setAmount($this->getAmount());
        $copyObj->setDonationAmount($this->getDonationAmount());
        $copyObj->setPaypalTransactionFee($this->getPaypalTransactionFee());
        $copyObj->setTotalAmount($this->getTotalAmount());
        $copyObj->setStatus($this->getStatus());
        $copyObj->setCustom($this->getCustom());
        $copyObj->setToken($this->getToken());
        $copyObj->setPaymentId($this->getPaymentId());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());
        if ($makeNew) {
            $copyObj->setNew(true);
            $copyObj->setId(NULL); // this is a auto-increment column, so set to default value
        }
    }

    /**
     * Makes a copy of this object that will be inserted as a new row in table when saved.
     * It creates a new object filling in the simple attributes, but skipping any primary
     * keys that are defined for the table.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param  boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @return \Zidisha\Payment\Paypal\PaypalTransaction Clone of current object.
     * @throws PropelException
     */
    public function copy($deepCopy = false)
    {
        // we use get_class(), because this might be a subclass
        $clazz = get_class($this);
        $copyObj = new $clazz();
        $this->copyInto($copyObj, $deepCopy);

        return $copyObj;
    }

    /**
     * Declares an association between this object and a ChildPayment object.
     *
     * @param  ChildPayment $v
     * @return $this|\Zidisha\Payment\Paypal\PaypalTransaction The current object (for fluent API support)
     * @throws PropelException
     */
    public function setPayment(ChildPayment $v = null)
    {
        if ($v === null) {
            $this->setPaymentId(NULL);
        } else {
            $this->setPaymentId($v->getId());
        }

        $this->aPayment = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildPayment object, it will not be re-added.
        if ($v !== null) {
            $v->addPaypalTransaction($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildPayment object
     *
     * @param  ConnectionInterface $con Optional Connection object.
     * @return ChildPayment The associated ChildPayment object.
     * @throws PropelException
     */
    public function getPayment(ConnectionInterface $con = null)
    {
        if ($this->aPayment === null && ($this->payment_id !== null)) {
            $this->aPayment = PaymentQuery::create()->findPk($this->payment_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aPayment->addPaypalTransactions($this);
             */
        }

        return $this->aPayment;
    }

    /**
     * Clears the current object, sets all attributes to their default values and removes
     * outgoing references as well as back-references (from other objects to this one. Results probably in a database
     * change of those foreign objects when you call `save` there).
     */
    public function clear()
    {
        if (null !== $this->aPayment) {
            $this->aPayment->removePaypalTransaction($this);
        }
        $this->id = null;
        $this->transaction_id = null;
        $this->transaction_type = null;
        $this->amount = null;
        $this->donation_amount = null;
        $this->paypal_transaction_fee = null;
        $this->total_amount = null;
        $this->status = null;
        $this->custom = null;
        $this->token = null;
        $this->payment_id = null;
        $this->created_at = null;
        $this->updated_at = null;
        $this->alreadyInSave = false;
        $this->clearAllReferences();
        $this->resetModified();
        $this->setNew(true);
        $this->setDeleted(false);
    }

    /**
     * Resets all references and back-references to other model objects or collections of model objects.
     *
     * This method is used to reset all php object references (not the actual reference in the database).
     * Necessary for object serialisation.
     *
     * @param      boolean $deep Whether to also clear the references on all referrer objects.
     */
    public function clearAllReferences($deep = false)
    {
        if ($deep) {
        } // if ($deep)

        $this->aPayment = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(PaypalTransactionTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     $this|ChildPaypalTransaction The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[PaypalTransactionTableMap::COL_UPDATED_AT] = true;

        return $this;
    }

    /**
     * Code to be run before persisting the object
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preSave(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after persisting the object
     * @param ConnectionInterface $con
     */
    public function postSave(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before inserting to database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after inserting to database
     * @param ConnectionInterface $con
     */
    public function postInsert(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before updating the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after updating the object in database
     * @param ConnectionInterface $con
     */
    public function postUpdate(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before deleting the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after deleting the object in database
     * @param ConnectionInterface $con
     */
    public function postDelete(ConnectionInterface $con = null)
    {

    }


    /**
     * Derived method to catches calls to undefined methods.
     *
     * Provides magic import/export method support (fromXML()/toXML(), fromYAML()/toYAML(), etc.).
     * Allows to define default __call() behavior if you overwrite __call()
     *
     * @param string $name
     * @param mixed  $params
     *
     * @return array|string
     */
    public function __call($name, $params)
    {
        if (0 === strpos($name, 'get')) {
            $virtualColumn = substr($name, 3);
            if ($this->hasVirtualColumn($virtualColumn)) {
                return $this->getVirtualColumn($virtualColumn);
            }

            $virtualColumn = lcfirst($virtualColumn);
            if ($this->hasVirtualColumn($virtualColumn)) {
                return $this->getVirtualColumn($virtualColumn);
            }
        }

        if (0 === strpos($name, 'from')) {
            $format = substr($name, 4);

            return $this->importFrom($format, reset($params));
        }

        if (0 === strpos($name, 'to')) {
            $format = substr($name, 2);
            $includeLazyLoadColumns = isset($params[0]) ? $params[0] : true;

            return $this->exportTo($format, $includeLazyLoadColumns);
        }

        throw new BadMethodCallException(sprintf('Call to undefined method: %s.', $name));
    }

}
