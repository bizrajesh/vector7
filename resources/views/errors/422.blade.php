@extends('errors.layout')
@section('code', '422')
@section('title', 'Action not allowed')
@section('message', $exception->getMessage() ?: 'This action is not allowed in the current state.')
