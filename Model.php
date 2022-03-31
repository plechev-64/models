<?php

/*
 * version: 1.0.0
 */

class Model {

	static $primaryKey = '';
	static $modelMeta = '';
	static $tableKeys = [];
	static $tableName = '';
	public $metadata = [];
	static $serialize = [];

	function __construct( $primary, $meta = [] ) {

		if ( is_object( $primary ) ) {
			foreach ( $primary as $k => $v ) {
				$this->$k = $v;
			}
		} else {

			$this->{static::$primaryKey} = $primary;

			$this->setupData();

			if ( ! empty( static::$modelMeta ) && $meta ) {
				$this->setupMeta( $meta );
			}
		}

	}

	static function query( $as = false ): DBQuery {
		global $wpdb;

		$cols = [];
		foreach ( static::$tableKeys as $key => $data ) {
			$cols[] = $key;
		}

		$table = [
			'name' => $wpdb->prefix . static::$tableName,
			'as'   => $as ?: static::$tableName,
			'cols' => $cols
		];

		$query = new DBQuery( $table );

		if(!empty(static::$serialize)){
			$query->serialize = static::$serialize;
		}

		return $query;

	}

	static function meta() {
		return static::$modelMeta;
	}

	function setupData() {

		$data = self::query()->where( [
			static::$primaryKey => ! empty( $this->{static::$primaryKey} ) ? $this->{static::$primaryKey} : null
		] )->get_row();

		if ( empty( $data ) ) {
			return false;
		}

		foreach ( $data as $k => $v ) {
			$this->$k = $v;
		}

	}

	function getMetaData( $meta = [] ) {

		$model = static::meta();

		return $model::query()->select( [
			$model::$originKeys['key'],
			$model::$originKeys['value']
		] )->where( [
			$model::$originKeys['item_id']      => $this->{static::$primaryKey},
			$model::$originKeys['key'] . '__in' => $meta && is_array( $meta ) ? $meta : null,
		] )->limit( - 1 )->get_results();

	}

	function setupMeta( $meta = [] ) {
		if ( $metadata = $this->getMetaData( $meta ) ) {
			$this->metadata = static::$modelMeta::setupMetaData( $metadata );
		}
	}

	function getMetaVal( $key ) {
		return isset( $this->metadata[ $key ] ) ? $this->metadata[ $key ] : false;
	}

	function updateItem( $update ) {
		return static::updateByPrimaryKey( $this->{static::$primaryKey}, $update );
	}

	function deleteItem() {
		return static::deleteData( [
			static::$primaryKey => $this->{static::$primaryKey}
		] );
	}

	static function updateMeta( $post_id, $data ) {

		if ( empty( static::$modelMeta ) ) {
			return false;
		}

		foreach ( $data as $key => $value ) {

			if ( isset( static::$modelMeta::$singleMetaKeys[ $key ] ) ) {

				static::$modelMeta::deleteValuesByKey( $post_id, $key );

				if ( $value != '' ) {
					static::$modelMeta::add( $post_id, $key, $value );
				}

			} else if ( isset( static::$modelMeta::$multiMetaKeys[ $key ] ) ) {

				static::$modelMeta::deleteValuesByKey( $post_id, $key );

				if ( is_array( $value ) && $value ) {
					foreach ( $value as $val ) {
						if ( $val ) {
							static::$modelMeta::add( $post_id, $key, $val );
						}
					}
				}

			}

		}

	}

	static function setDefaults( $args, $update = false ) {

		foreach ( static::$tableKeys as $key => $default ) {
			if ( ! $update && empty( $args[ $key ] ) ) {
				$args[ $key ] = $default;
			}

			if ( $update && isset( $args[ $key ] ) && ! $args[ $key ] ) {
				$args[ $key ] = $default;
			}
		}

		return $args;
	}

	static function insertData( $insert ) {
		global $wpdb;

		$insert = static::setDefaults( $insert );

		if(!empty(static::$serialize)){
			foreach(static::$serialize as $key){
				$insert[$key] = maybe_serialize($insert[$key]);
			}
		}

		if ( ! $wpdb->insert( $wpdb->prefix . static::$tableName, $insert ) ) {
			print_r( [ $wpdb->prefix . static::$tableName, $insert ] );
			exit;
		}

		return $wpdb->insert_id;

	}

	static function updateData( $update, $where ) {
		global $wpdb;

		if ( ! static::query()->where( $where )->get_row() ) {
			return static::insertData( array_merge( $where, $update ) );
		}

		if(!empty(static::$serialize)){
			foreach(static::$serialize as $key){
				$update[$key] = maybe_serialize($update[$key]);
			}
		}

		$update = static::setDefaults( $update, true );

		return $wpdb->update(
			$wpdb->prefix . static::$tableName, $update, $where
		);

	}

	static function updateByPrimaryKey( $primaryKey, $update ) {
		return static::updateData( $update, [
			static::$primaryKey => $primaryKey
		] );
	}

	static function updateCommon( $id, $updateData ) {

		if ( ! empty( static::$modelMeta ) ) {
			static::updateMeta( $id, $updateData );
		}

		if ( $tableFields = static::getMainFieldsValues( $updateData ) ) {
			static::updateByPrimaryKey( $id, $tableFields );
		}

	}

	static function deleteData( $where ) {
		global $wpdb;

		return $wpdb->delete(
			$wpdb->prefix . static::$tableName, $where
		);

	}

	static function getMainFieldsValues( $data ) {

		$tableFields = [];

		foreach ( $data as $key => $value ) {

			if ( isset( static::$tableKeys[ $key ] ) ) {
				$tableFields[ $key ] = $value;
			}

		}

		return $tableFields;

	}

}