# 定义远程仓库的名称
$remotes = @("origin", "local")

# 循环推送所有分支到每个远程仓库
foreach ($remote in $remotes) {
    # 推送所有分支
    git push $remote --all

    # 如果需要，也推送所有的标签
    git push $remote --tags
}

# 如果需要强制推送，可以用下面的命令替换上面的git push命令：
# git push $remote --all --force
# git push $remote --tags --force