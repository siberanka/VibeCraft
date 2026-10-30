@extends('errors::layout', ['statusCode' => 429, 'heading' => __('Take a short break'), 'description' => __('Too many requests arrived together. Wait a little before trying again.')])
