#!/bin/env bash
docker build -t laravel-model-browser-test . \
  && docker run --rm laravel-model-browser-test
