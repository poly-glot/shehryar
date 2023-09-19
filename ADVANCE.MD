## Advance Topics

### Running application on local kubernetes cluster

#### Setting up kubernetes cluster (one off)
```shell
# 01 - Start Minikube
minikube start

# 02 - Setting up traefik (ingress)
helm repo add traefik https://traefik.github.io/charts
helm repo update

curl https://raw.githubusercontent.com/poly-glot/personal-cloud/main/terraform/02-traefik/values.local.yaml -o values.local.yaml 

helm upgrade --install traefik traefik/traefik \
--namespace kube-system --create-namespace -f values.local.yaml

# 03 - Setup Primary Database
kubectl create namespace database
kubectl apply -f deployment/base/components/database/mysql.local.yaml --namespace database

## View status of pod
kubectl get pods --selector "app=mysql" --namespace database
kubectl describe $(kubectl get pods --selector "app=mysql" --output=name --namespace database) --namespace database
kubectl logs $(kubectl get pods --selector "app=mysql" --output=name --namespace database) --namespace database


# 04 - Setup database and import
kubectl exec --stdin --tty $(kubectl get pods --selector "app=mysql" --output=name --namespace database) --namespace database -- /bin/bash
mysql -uroot -proot
CREATE DATABASE chatapp;
```

#### Building Docker image
Use the [guide](https://minikube.sigs.k8s.io/docs/handbook/pushing/) to push docker image to minikube based on your OS.
```shell
eval $(minikube docker-env)
docker build -t shehryar:latest .
```

##### Deploy to local cluster
```shell
kubectl apply -k deployment/overlays/local
kubectl get pods -n shehryar-local

# access locally 
ddev poweroff 
sudo kubectl port-forward service/traefik 80:80 443:443 -n kube-system

# access phpmyadmin by running following command and vi

```

##### Access phpMyAdmin on Cluster
1. Run following command to launch phpMyAdmin
```shell
kubectl port-forward $(kubectl get pods --selector "tool=db" --output=name -n shehryar-local) -n  shehryar-local 9010:80
```
2. Visit http://localhost:9010
3. Enter Database username/password (root/root)

# Useful commands
```shell
# to view traefik dashboard - http://localhost:9000/dashboard
kubectl port-forward $(kubectl get pods --selector "app.kubernetes.io/name=traefik" --output=name -n kube-system) -n kube-system 9000:9000

# To view which application is listening on port 80
sudo lsof -i -P | grep LISTEN | grep :80
```

