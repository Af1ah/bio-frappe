FROM golang:1.26.2-bookworm AS build
WORKDIR /src
COPY go.mod go.sum ./
RUN go mod download
COPY *.go ./
RUN mkdir -p /out/data && CGO_ENABLED=0 go build -trimpath -ldflags='-s -w' -o /out/adms-gateway .

FROM gcr.io/distroless/static-debian12:nonroot
COPY --from=build /out/adms-gateway /adms-gateway
COPY --from=build --chown=nonroot:nonroot /out/data /var/lib/bio-notifier-adms
EXPOSE 8080 8081
USER nonroot:nonroot
ENTRYPOINT ["/adms-gateway"]
