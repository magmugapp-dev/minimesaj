abstract interface class UseCase<Result, Params> {
  Future<Result> call(Params params);
}

final class NoParams {
  const NoParams();
}
