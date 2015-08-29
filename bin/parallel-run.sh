#!/bin/bash
set -m

if [[ $1 > 1 ]]; then
  START_TIME=$SECONDS
  for ((i=0;i<$1;i++))
  do
    ./run.sh 1 $2 $i $4 $5 $6 $7 $8 $9 &
  done
  
  while [ 1 ]; do fg 2> /dev/null; [ $? == 1 ] && break; ELAPSED_TIME=$(($SECONDS - $START_TIME)); echo "All processes done. Elapsed Time: $ELAPSED_TIME"; done
  exit
fi

echo "Process $3 is started"

for ((n=0;n<$2;n++))
do
 echo "update $n of process $3 started"
 bin/magento dev:single-row-indexer --indexer=$4 --reindex-id=$5 --max-categories=$6 --max-products=$7 --max-customers=$8 --max-rules=$9
done
