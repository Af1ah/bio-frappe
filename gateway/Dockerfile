FROM golang:1.26.2-bookworm AS build
WORKDIR /src
COPY go.mod ./
RUN go mod download
COPY main.go ./
RUN go mod tidy && CGO_ENABLED=0 go build -trimpath -ldflags='-s -w' -o /out/adms-gateway .

FROM gcr.io/distroless/static-debian12:nonroot
COPY --from=build /out/adms-gateway /adms-gateway
EXPOSE 8080
USER nonroot:nonroot
ENTRYPOINT ["/adms-gateway"]
